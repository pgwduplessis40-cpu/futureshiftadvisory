<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Services\Voice\Contracts\WhisperClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VoiceNoteProcessor
{
    public function __construct(
        private readonly WhisperClient $whisper,
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    public function processDocument(Client $client, Document $document, ?User $actor = null, ?Milestone $milestone = null): VoiceNote
    {
        if ((string) $document->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('Voice-note document must belong to the client.');
        }

        return DB::transaction(function () use ($client, $document, $actor, $milestone): VoiceNote {
            /** @var VoiceNote $voiceNote */
            $voiceNote = VoiceNote::query()->create([
                'client_id' => $client->getKey(),
                'document_id' => $document->getKey(),
                'uploaded_by_user_id' => $actor?->getAuthIdentifier(),
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'status' => VoiceNote::STATUS_UPLOADED,
            ]);

            $transcript = $this->whisper->transcribe($document);
            $voiceNote->forceFill([
                'transcription_text' => $transcript['text'],
                'transcription_metadata' => $transcript['metadata'],
                'status' => VoiceNote::STATUS_TRANSCRIBED,
                'transcribed_at' => now(),
            ])->save();

            $summary = $this->summarise($client, $voiceNote, $transcript['text'], $milestone);
            $callLog = $this->createCallLogFromSummary($client, $voiceNote, $summary, $actor, $milestone);

            $voiceNote->forceFill([
                'summary_text' => $callLog->summary,
                'summary_payload' => $summary,
                'status' => VoiceNote::STATUS_SUMMARIZED,
                'summarized_at' => now(),
            ])->save();

            $this->audit->record('voice_note.processed', subject: $voiceNote, actor: $actor, after: [
                'call_log_id' => $callLog->getKey(),
                'document_id' => $document->getKey(),
                'action_items' => count($callLog->action_items ?? []),
            ]);

            return $voiceNote->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function recordCallLog(Client $client, array $input, ?User $actor = null): CallLog
    {
        return DB::transaction(function () use ($client, $input, $actor): CallLog {
            /** @var CallLog $callLog */
            $callLog = CallLog::query()->create([
                'client_id' => $client->getKey(),
                'advisor_user_id' => $actor?->getAuthIdentifier(),
                'title' => $this->required($input, 'title'),
                'channel' => (string) ($input['channel'] ?? CallLog::CHANNEL_PHONE_CALL),
                'occurred_at' => $input['occurred_at'] ?? now(),
                'transcript' => $input['transcript'] ?? null,
                'summary' => $input['summary'] ?? null,
                'action_items' => [],
            ]);

            $actionItems = $this->linkActionItems($callLog, is_array($input['action_items'] ?? null) ? $input['action_items'] : [], $actor);
            $callLog->forceFill(['action_items' => $actionItems])->save();

            $this->audit->record('call_log.created', subject: $callLog, actor: $actor, after: [
                'action_items' => count($actionItems),
            ]);

            return $callLog->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Client $client, VoiceNote $voiceNote, string $transcript, ?Milestone $milestone): array
    {
        $prompt = new PromptEnvelope(
            id: 'voice_note.summary',
            version: '1',
            task: 'Summarise a client voice note and extract action items linked to milestones when provided.',
            body: 'Return a concise meeting note summary and action_items with title, due_date, priority, and milestone_id when applicable.',
            input: [
                'client_id' => $client->getKey(),
                'voice_note_id' => $voiceNote->getKey(),
                'transcript' => $transcript,
                'default_milestone_id' => $milestone?->getKey(),
            ],
            sourceReferences: ['voice_note:'.$voiceNote->getKey()],
        );
        $response = $this->ai->summarise($prompt);

        return $this->summaryPayload($response, $milestone);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(AiResponse $response, ?Milestone $milestone): array
    {
        $payload = is_array($response->metadata['summary_payload'] ?? null)
            ? $response->metadata['summary_payload']
            : null;

        if ($payload === null) {
            $decoded = json_decode($response->text, true);
            $payload = is_array($decoded) ? $decoded : ['summary' => $response->text, 'action_items' => []];
        }

        $actionItems = collect(is_array($payload['action_items'] ?? null) ? $payload['action_items'] : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($milestone): array {
                $item['milestone_id'] ??= $milestone?->getKey();
                $item['priority'] ??= 'normal';

                return $item;
            })
            ->values()
            ->all();

        return [
            'summary' => (string) ($payload['summary'] ?? $response->text),
            'decisions' => is_array($payload['decisions'] ?? null) ? $payload['decisions'] : [],
            'action_items' => $actionItems,
            'model' => $response->model,
            'prompt_hash' => $response->promptHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function createCallLogFromSummary(
        Client $client,
        VoiceNote $voiceNote,
        array $summary,
        ?User $actor,
        ?Milestone $milestone,
    ): CallLog {
        /** @var CallLog $callLog */
        $callLog = CallLog::query()->create([
            'client_id' => $client->getKey(),
            'voice_note_id' => $voiceNote->getKey(),
            'advisor_user_id' => $actor?->getAuthIdentifier(),
            'title' => $voiceNote->original_filename ?: 'Voice note',
            'channel' => CallLog::CHANNEL_VOICE_NOTE,
            'occurred_at' => now(),
            'transcript' => $voiceNote->transcription_text,
            'summary' => (string) $summary['summary'],
            'action_items' => [],
        ]);

        $actionItems = $this->linkActionItems($callLog, $summary['action_items'] ?? [], $actor, $milestone);
        $callLog->forceFill(['action_items' => $actionItems])->save();

        return $callLog->refresh();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function linkActionItems(CallLog $callLog, array $items, ?User $actor, ?Milestone $defaultMilestone = null): array
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($callLog, $actor, $defaultMilestone): array {
                $milestoneId = (string) ($item['milestone_id'] ?? $defaultMilestone?->getKey() ?? '');
                $title = trim((string) ($item['title'] ?? ''));

                if ($milestoneId === '' || $title === '') {
                    return $item;
                }

                $milestone = Milestone::query()
                    ->where('client_id', $callLog->client_id)
                    ->whereKey($milestoneId)
                    ->first();

                if (! $milestone instanceof Milestone) {
                    return $item;
                }

                /** @var MilestoneAction $action */
                $action = MilestoneAction::query()->create([
                    'milestone_id' => $milestone->getKey(),
                    'client_id' => $callLog->client_id,
                    'call_log_id' => $callLog->getKey(),
                    'title' => $title,
                    'owner_user_id' => $actor?->getAuthIdentifier(),
                    'due_date' => Arr::get($item, 'due_date'),
                    'priority' => (string) ($item['priority'] ?? 'normal'),
                    'status' => MilestoneAction::STATUS_PENDING,
                ]);

                $this->audit->record('call_log.action_linked', subject: $action, actor: $actor, after: [
                    'call_log_id' => $callLog->getKey(),
                    'milestone_id' => $milestone->getKey(),
                ]);

                return [
                    ...$item,
                    'milestone_id' => $milestone->getKey(),
                    'milestone_action_id' => $action->getKey(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function required(array $input, string $key): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }
}
