<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\EngagementType;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryDraft;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KnowledgeCaptureDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        Notification::fake();
        $this->app->bind(AiClient::class, FakeAiClient::class);
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_offboarding_completion_creates_pending_draft_without_live_entry(): void
    {
        [$advisor, $client] = $this->clientWithTeam();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.offboarding.store', $client), [
                'exit_interview_notes' => 'Reusable pricing review lesson.',
                'handover_notes' => 'Follow-up owner cadence.',
                'privacy_acknowledged' => true,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $record = OffboardingRecord::query()->firstOrFail();
        $draft = KnowledgeEntryDraft::query()->firstOrFail();

        $this->assertSame($advisor->id, $draft->author_user_id);
        $this->assertSame($client->id, $draft->client_id);
        $this->assertSame(KnowledgeEntryDraft::STATE_PENDING, $draft->state);
        $this->assertSame(KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD, $draft->source_type);
        $this->assertSame($record->id, $draft->source_id);
        $this->assertSame(FakeAiClient::DEGRADED_TEXT, $draft->body);
        $this->assertDatabaseCount('knowledge_entries', 0);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.knowledge.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/knowledge/Index')
                ->has('entries', 0)
                ->has('drafts', 1)
                ->where('drafts.0.id', $draft->id)
                ->where('drafts.0.state', KnowledgeEntryDraft::STATE_PENDING));
    }

    public function test_manual_completed_offboarding_capture_reuses_pending_draft(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $record = $this->completedOffboarding($advisor, $client);
        $draft = $this->pendingDraft($advisor, $client, $record->id);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.knowledge-drafts.store', $client))
            ->assertRedirect(route('advisor.knowledge-drafts.review', $draft, absolute: false));

        $this->assertDatabaseCount('knowledge_entry_drafts', 1);
        $this->assertSame(KnowledgeEntryDraft::STATE_PENDING, $draft->refresh()->state);
    }

    public function test_accepting_draft_creates_live_entry_and_marks_draft_accepted(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $draft = $this->pendingDraft($advisor, $client);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.knowledge-drafts.accept', $draft), [
                'client_id' => $client->id,
                'category' => KnowledgeEntry::CATEGORY_METHODOLOGY,
                'title' => 'Edited pattern',
                'body' => 'Reusable lesson after advisor review.',
                'tags' => 'accepted, edited',
            ])
            ->assertRedirect();

        $entry = KnowledgeEntry::query()->firstOrFail();
        $draft->refresh();

        $this->assertSame($advisor->id, $entry->author_user_id);
        $this->assertSame('Edited pattern', $entry->title);
        $this->assertSame(['accepted', 'edited'], $entry->tags);
        $this->assertSame(KnowledgeEntryDraft::STATE_ACCEPTED, $draft->state);
        $this->assertSame($entry->id, $draft->accepted_entry_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'knowledge_entry_draft.accepted',
            'subject_id' => $draft->id,
        ]);
    }

    public function test_discarding_draft_leaves_no_live_entry(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $draft = $this->pendingDraft($advisor, $client);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.knowledge-drafts.discard', $draft))
            ->assertRedirect(route('advisor.knowledge.index', absolute: false));

        $this->assertDatabaseCount('knowledge_entries', 0);
        $this->assertSame(KnowledgeEntryDraft::STATE_DISCARDED, $draft->refresh()->state);
    }

    public function test_drafts_are_scoped_to_their_author(): void
    {
        [$owner, $client] = $this->clientWithTeam();
        $otherAdvisor = $this->advisor('other@example.com');
        $draft = $this->pendingDraft($owner, $client);

        $read = $this->actingAsMfa($otherAdvisor)
            ->get(route('advisor.knowledge-drafts.review', $draft));

        $this->assertContains($read->getStatusCode(), [403, 404]);

        $write = $this->actingAsMfa($otherAdvisor)
            ->patch(route('advisor.knowledge-drafts.accept', $draft), [
                'client_id' => $client->id,
                'category' => KnowledgeEntry::CATEGORY_METHODOLOGY,
                'title' => 'Stolen draft',
                'body' => 'Attempted mutation.',
                'tags' => '',
            ]);

        $this->assertContains($write->getStatusCode(), [403, 404]);
        $this->assertDatabaseCount('knowledge_entries', 0);
        $this->assertSame(KnowledgeEntryDraft::STATE_PENDING, $draft->refresh()->state);
    }

    public function test_source_attribution_is_kept_and_audit_payload_omits_client_pii(): void
    {
        [$advisor, $client] = $this->clientWithTeam(legalName: 'Sensitive Client Limited');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.offboarding.store', $client), [
                'exit_interview_notes' => 'Sensitive owner email owner@example.test should stay out of audit payloads.',
                'handover_notes' => 'Do not publish raw handover notes.',
                'privacy_acknowledged' => true,
            ])
            ->assertRedirect();

        $draft = KnowledgeEntryDraft::query()->firstOrFail();
        $attribution = $draft->source_attribution;

        $this->assertSame(KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD, $attribution['source_type'] ?? null);
        $this->assertSame($draft->source_reference, $attribution['source_reference'] ?? null);
        $this->assertSame('fake-ai-client', $attribution['ai']['model'] ?? null);
        $this->assertTrue($attribution['human_review_required'] ?? false);
        $this->assertTrue($attribution['client_pii_excluded_from_prompt'] ?? false);

        $audit = AuditEvent::query()
            ->where('action', 'knowledge_entry_draft.created')
            ->firstOrFail();
        $payload = json_encode($audit->after, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('Sensitive Client Limited', $payload);
        $this->assertStringNotContainsString('owner@example.test', $payload);
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithTeam(?User $advisor = null, string $legalName = 'Offboarding Test Limited'): array
    {
        $advisor ??= $this->advisor();

        $clientUser = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => 'client.owner@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000300',
            'legal_name' => $legalName,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client, $clientUser];
    }

    private function advisor(string $email = 'advisor@example.com'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function completedOffboarding(User $advisor, Client $client): OffboardingRecord
    {
        return OffboardingRecord::query()->create([
            'client_id' => $client->id,
            'triggered_by_user_id' => $advisor->id,
            'status' => OffboardingRecord::STATUS_COMPLETED,
            'triggered_at' => now(),
            'final_report_path' => 'offboarding/final.pdf',
            'engagement_summary_path' => 'offboarding/summary.pdf',
            'handover_path' => 'offboarding/handover.pdf',
            'exit_interview_path' => 'offboarding/exit.pdf',
            'privacy_notice_path' => 'offboarding/privacy.pdf',
            'reengagement_due' => now()->addDays(90),
            'advisor_capacity_released' => true,
            'metadata' => ['phase' => 1],
        ]);
    }

    private function pendingDraft(User $advisor, Client $client, ?string $sourceId = null): KnowledgeEntryDraft
    {
        $sourceId ??= (string) Str::uuid();

        return KnowledgeEntryDraft::query()->create([
            'author_user_id' => $advisor->getKey(),
            'client_id' => $client->getKey(),
            'source_type' => KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD,
            'source_id' => $sourceId,
            'source_reference' => 'offboarding_record:'.$sourceId,
            'category' => KnowledgeEntry::CATEGORY_CLIENT_PATTERN,
            'title' => 'Draft lesson',
            'body' => 'Pending advisor review.',
            'tags' => ['ai-draft', 'offboarding'],
            'source_attribution' => [
                'source_type' => KnowledgeEntryDraft::SOURCE_OFFBOARDING_RECORD,
                'source_reference' => 'offboarding_record:'.$sourceId,
                'human_review_required' => true,
            ],
            'state' => KnowledgeEntryDraft::STATE_PENDING,
        ]);
    }
}
