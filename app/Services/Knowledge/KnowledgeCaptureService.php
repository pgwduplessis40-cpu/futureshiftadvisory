<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryDraft;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KnowledgeCaptureService
{
    public function __construct(
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    public function captureFromOffboarding(OffboardingRecord $record, ?User $actor = null): ?KnowledgeEntryDraft
    {
        $record->loadMissing(['client.teamMembers.user', 'triggeredBy']);
        $client = $record->client;

        if (! $client instanceof Client) {
            return null;
        }

        $author = $this->advisorFor($client, $actor ?? $record->triggeredBy);
        if (! $author instanceof User) {
            return null;
        }

        $response = $this->ai->summarise($this->prompt($record, $client));
        $sourceReference = 'offboarding_record:'.$record->getKey();
        $attributes = [
            'author_user_id' => $author->getKey(),
            'client_id' => $client->getKey(),
            'source_type' => KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD,
            'source_id' => $record->getKey(),
            'source_reference' => $sourceReference,
            'category' => KnowledgeEntry::CATEGORY_CLIENT_PATTERN,
            'title' => $this->draftTitle($client),
            'body' => $this->draftBody($response->text),
            'tags' => $this->draftTags($client),
            'source_attribution' => [
                'source_reference' => $sourceReference,
                'source_type' => KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD,
                'source_id' => (string) $record->getKey(),
                'client_id' => (string) $client->getKey(),
                'ai' => [
                    'model' => $response->model,
                    'prompt_version' => $response->promptVersion,
                    'prompt_hash' => $response->promptHash,
                    'uncertainty' => $response->uncertainty->value,
                    'attributions' => $response->attributions,
                ],
                'human_review_required' => true,
                'client_pii_excluded_from_title' => true,
                'client_pii_excluded_from_prompt' => true,
            ],
        ];

        $draft = DB::transaction(function () use ($attributes, $author, $record): KnowledgeEntryDraft {
            $existing = KnowledgeEntryDraft::query()
                ->where('author_user_id', $author->getKey())
                ->where('source_type', KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD)
                ->where('source_id', $record->getKey())
                ->first();

            if ($existing instanceof KnowledgeEntryDraft) {
                if ($existing->state === KnowledgeEntryDraft::STATE_PENDING) {
                    $existing->forceFill($attributes)->save();
                }

                return $existing->refresh();
            }

            $draft = KnowledgeEntryDraft::query()->create([
                ...$attributes,
                'state' => KnowledgeEntryDraft::STATE_PENDING,
            ]);

            $this->audit->record('knowledge_entry_draft.created', subject: $draft, actor: $author, after: [
                'draft_id' => $draft->getKey(),
                'source_type' => $draft->source_type,
                'source_id' => $draft->source_id,
                'author_user_id' => $author->getKey(),
            ]);

            return $draft;
        });

        return $draft;
    }

    /**
     * @param  array{client_id?: string|null, category: string, title: string, body: string, tags: array<int, string>}  $attributes
     */
    public function accept(KnowledgeEntryDraft $draft, User $actor, array $attributes): KnowledgeEntry
    {
        abort_unless($draft->state === KnowledgeEntryDraft::STATE_PENDING, 409, 'Only pending knowledge drafts can be accepted.');

        return DB::transaction(function () use ($actor, $attributes, $draft): KnowledgeEntry {
            $entry = KnowledgeEntry::query()->create([
                'author_user_id' => $draft->author_user_id,
                'client_id' => $attributes['client_id'] ?? null,
                'category' => $attributes['category'],
                'title' => trim($attributes['title']),
                'body' => trim($attributes['body']),
                'tags' => $attributes['tags'],
            ]);

            $draft->forceFill([
                'client_id' => $entry->client_id,
                'category' => $entry->category,
                'title' => $entry->title,
                'body' => $entry->body,
                'tags' => $entry->tags,
                'state' => KnowledgeEntryDraft::STATE_ACCEPTED,
                'accepted_entry_id' => $entry->getKey(),
            ])->save();

            $this->audit->record('knowledge_entry_draft.accepted', subject: $draft, actor: $actor, after: [
                'draft_id' => $draft->getKey(),
                'knowledge_entry_id' => $entry->getKey(),
                'author_user_id' => $draft->author_user_id,
            ]);

            return $entry;
        });
    }

    public function discard(KnowledgeEntryDraft $draft, User $actor): void
    {
        abort_unless($draft->state === KnowledgeEntryDraft::STATE_PENDING, 409, 'Only pending knowledge drafts can be discarded.');

        $draft->forceFill([
            'state' => KnowledgeEntryDraft::STATE_DISCARDED,
        ])->save();

        $this->audit->record('knowledge_entry_draft.discarded', subject: $draft, actor: $actor, after: [
            'draft_id' => $draft->getKey(),
            'author_user_id' => $draft->author_user_id,
        ]);
    }

    private function prompt(OffboardingRecord $record, Client $client): PromptEnvelope
    {
        $metadata = is_array($record->metadata) ? $record->metadata : [];
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type->label()
            : (string) $client->engagement_type;

        return new PromptEnvelope(
            id: 'knowledge-capture.offboarding',
            version: 'v1',
            task: 'Draft an advisor-owned knowledge-base entry from an offboarding record. Generalise the lesson, exclude client names and personal data, and keep it pending human review.',
            body: implode("\n\n", [
                'Source: completed offboarding record.',
                'Engagement type: '.$engagementType,
                'Exit notes: '.$this->promptNote($metadata['exit_interview_notes'] ?? '', $client),
                'Handover notes: '.$this->promptNote($metadata['handover_notes'] ?? '', $client),
            ]),
            input: [
                'source_type' => KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD,
                'source_id' => (string) $record->getKey(),
                'engagement_type' => $engagementType,
                'privacy' => 'Do not include client names, user names, emails, or other personal data in the draft.',
            ],
            sourceReferences: ['offboarding_record:'.$record->getKey()],
        );
    }

    private function promptNote(mixed $value, Client $client): string
    {
        $text = (string) $value;
        $redacted = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email redacted]', $text) ?? $text;

        foreach ([$client->legal_name, $client->trading_name] as $name) {
            if (is_string($name) && trim($name) !== '') {
                $redacted = str_ireplace($name, '[client redacted]', $redacted);
            }
        }

        return Str::limit($redacted, 3000);
    }

    private function advisorFor(Client $client, ?User $fallback): ?User
    {
        if ($fallback instanceof User && $fallback->user_type === User::TYPE_ADVISOR) {
            return $fallback;
        }

        $client->loadMissing('teamMembers.user');

        $lead = $client->teamMembers
            ->first(fn (ClientTeamMember $member): bool => $member->role === 'lead_advisor'
                && $member->user instanceof User
                && $member->user->user_type === User::TYPE_ADVISOR)
            ?->user;

        if ($lead instanceof User) {
            return $lead;
        }

        return $client->teamMembers
            ->first(fn (ClientTeamMember $member): bool => $member->user instanceof User
                && $member->user->user_type === User::TYPE_ADVISOR)
            ?->user;
    }

    private function draftTitle(Client $client): string
    {
        $type = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type->label()
            : Str::headline((string) $client->engagement_type);

        return Str::limit("Post-engagement pattern: {$type}", 180, '');
    }

    private function draftBody(string $text): string
    {
        $body = trim($text);

        return $body !== ''
            ? $body
            : 'Draft pending advisor review. Capture the reusable engagement lesson before saving this entry.';
    }

    /**
     * @return array<int, string>
     */
    private function draftTags(Client $client): array
    {
        $type = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type->value
            : (string) $client->engagement_type;

        return array_values(array_unique([
            'ai-draft',
            'offboarding',
            Str::of($type)->replace('_', '-')->lower()->value(),
        ]));
    }
}
