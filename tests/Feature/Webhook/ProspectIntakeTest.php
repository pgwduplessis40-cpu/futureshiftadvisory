<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Models\InviteToken;
use App\Models\ProspectLead;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProspectIntakeTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-prospect-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Config::set('security.prospect_intake_secret', $this->secret);
        app(RequestContext::class)->apply('system', []);
        Mail::fake();
    }

    public function test_valid_signed_intake_creates_prospect_lead_and_notifies_advisor(): void
    {
        $advisor = $this->advisor();
        $payload = $this->payload([
            'name' => 'Ada Founder',
            'email' => 'ada@example.test',
            'source' => 'start_business_journey',
            'dedupe_key' => 'website-event-123',
        ]);

        $this->withHeaders($this->signatureHeaders($payload))
            ->postJson(route('webhooks.prospects.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('prospect_lead.status', ProspectLead::STATUS_NEW);

        $lead = ProspectLead::query()->firstOrFail();

        $this->assertSame('Ada Founder', $lead->name);
        $this->assertSame('ada@example.test', $lead->email);
        $this->assertSame('website-event-123', $lead->dedupe_key);
        $this->assertSame($advisor->getKey(), $lead->assigned_advisor_user_id);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $advisor->getKey(),
            'type' => 'prospect.lead.received',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'prospect_intake.received',
            'subject_id' => (string) $lead->id,
        ]);
    }

    public function test_invalid_hmac_is_rejected_and_audited(): void
    {
        $this->advisor();
        $payload = $this->payload();

        $this->withHeaders([
            'X-FSA-Timestamp' => (string) now()->getTimestamp(),
            'X-FSA-Signature' => 'sha256=invalid',
        ])
            ->postJson(route('webhooks.prospects.store'), $payload)
            ->assertUnauthorized();

        $this->assertDatabaseCount('prospect_leads', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'prospect_intake.signature_rejected',
        ]);
    }

    public function test_advisor_can_view_and_invite_from_prospect_inbox(): void
    {
        $advisor = $this->advisor();
        $lead = ProspectLead::query()->create([
            'name' => 'Kai Owner',
            'email' => 'kai@example.test',
            'company' => 'Kai Ventures',
            'engagement_interest' => 'standard_advisory',
            'message' => 'We are ready to talk.',
            'source' => 'request_advisory_conversation',
            'status' => ProspectLead::STATUS_NEW,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.prospects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/prospects/Index')
                ->where('leads.0.name', 'Kai Owner')
                ->where('inviteOptions.0.value', 'business_idea')
                ->where('inviteOptions.1.value', 'buying_business')
                ->where('canTriage', true));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.prospects.triage', $lead), [
                'outcome' => ProspectLead::STATUS_INVITED,
                'invite_path' => 'buying_business',
                'triage_notes' => 'Good fit for buying-a-business support.',
            ])
            ->assertRedirect(route('advisor.prospects.index', absolute: false));

        $lead->refresh();
        $invite = InviteToken::query()->firstOrFail();

        $this->assertSame(ProspectLead::STATUS_INVITED, $lead->status);
        $this->assertSame(ProspectLead::STATUS_INVITED, $lead->triage_outcome);
        $this->assertSame('Good fit for buying-a-business support.', $lead->triage_notes);
        $this->assertSame($advisor->getKey(), $lead->triaged_by_user_id);
        $this->assertSame($invite->getKey(), $lead->invite_token_id);
        $this->assertSame('kai@example.test', $invite->email);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $invite->target_user_type);
        $this->assertSame(ServiceActivation::SERVICE_DUE_DILIGENCE, $invite->intended_service_type);
        $this->assertNull($invite->intended_package_scope);
        $this->assertSame('Buying a Business', $invite->serviceIntentLabel());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'prospect_lead.triaged',
            'subject_id' => (string) $lead->id,
        ]);
    }

    public function test_advisor_business_idea_invite_sets_entrepreneur_access_intent(): void
    {
        $advisor = $this->advisor();
        $lead = $this->lead('idea-founder@example.test');

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.prospects.triage', $lead), [
                'outcome' => ProspectLead::STATUS_INVITED,
                'invite_path' => 'business_idea',
                'triage_notes' => 'Start with idea validation.',
            ])
            ->assertRedirect(route('advisor.prospects.index', absolute: false));

        $invite = InviteToken::query()->firstOrFail();

        $this->assertSame(User::TYPE_ENTREPRENEUR, $invite->target_user_type);
        $this->assertSame(ServiceActivation::SERVICE_ENTREPRENEUR, $invite->intended_service_type);
        $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION, $invite->intended_package_scope);
        $this->assertSame('Business Idea', $invite->serviceIntentLabel());
    }

    public function test_advisor_can_record_parked_and_declined_triage_outcomes(): void
    {
        $advisor = $this->advisor();
        $parked = $this->lead('parked@example.test');
        $declined = $this->lead('declined@example.test');

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.prospects.triage', $parked), [
                'outcome' => ProspectLead::STATUS_PARKED,
                'triage_notes' => 'Circle back next quarter.',
            ])
            ->assertRedirect();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.prospects.triage', $declined), [
                'outcome' => ProspectLead::STATUS_DECLINED,
                'triage_notes' => 'Outside current scope.',
            ])
            ->assertRedirect();

        $this->assertSame(ProspectLead::STATUS_PARKED, $parked->refresh()->triage_outcome);
        $this->assertSame(ProspectLead::STATUS_DECLINED, $declined->refresh()->triage_outcome);
        $this->assertDatabaseCount('invite_tokens', 0);
    }

    private function advisor(string $email = 'advisor@example.test'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function lead(string $email): ProspectLead
    {
        return ProspectLead::query()->create([
            'name' => 'Prospect '.$email,
            'email' => $email,
            'message' => 'Please review this lead.',
            'source' => 'request_advisory_conversation',
            'status' => ProspectLead::STATUS_NEW,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Mira Prospect',
            'email' => 'mira@example.test',
            'phone' => '+64 21 000 000',
            'company' => 'Mira Limited',
            'engagement_interest' => 'entrepreneur_module',
            'message' => 'I want to start a business journey.',
            'source' => 'start_business_journey',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signatureHeaders(array $payload): array
    {
        $timestamp = (string) now()->getTimestamp();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'X-FSA-Timestamp' => $timestamp,
            'X-FSA-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $this->secret),
        ];
    }
}
