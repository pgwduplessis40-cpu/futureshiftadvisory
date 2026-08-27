<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Mail\InvitationMail;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IndustryBriefing;
use App\Models\InviteToken;
use App\Models\KnowledgeAssessment;
use App\Models\Meeting;
use App\Models\NpoEngagement;
use App\Models\PreMeetingBrief;
use App\Models\Questionnaire;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AddClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_can_lookup_nzbn_registry_data_on_create_form(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.lookup-nzbn'), [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => '9429000000000',
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/clients/Create')
                ->where('lookup.summary.legal_name', 'Future Shift Advisory Test Limited')
                ->where('lookup.summary.gst_registered', null)
                ->where('lookup.summary.gst_registration_status', 'Client supplied - not verified with IRD')
                ->where('lookup.source_badges.nzbn', 'stub')
                ->where('lookup.source_badges.ird', 'client_supplied_not_ird_verified')
                ->where('defaults.legal_name', 'Future Shift Advisory Test Limited')
            );
    }

    public function test_advisor_can_create_client_with_registry_data_and_conflict_declaration(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.store'), [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => '9429000000000',
                'legal_name' => '',
                'trading_name' => 'Future Shift',
                'entity_type' => '',
                'conflict' => [
                    'declared' => true,
                    'referral_type' => 'client_creation',
                    'existing_relationship' => false,
                    'details' => null,
                ],
            ])
            ->assertRedirect();

        $client = Client::query()->firstOrFail();

        $this->assertSame('Future Shift Advisory Test Limited', $client->legal_name);
        $this->assertSame('Future Shift', $client->trading_name);
        $this->assertSame(EngagementType::STANDARD_ADVISORY, $client->engagement_type);
        $this->assertSame(Client::DATA_QUALITY_INSUFFICIENT, $client->data_quality);
        $this->assertFalse($client->gst_registered);
        $this->assertSame('stub', $client->registry_sources['nzbn']);
        $this->assertSame('client_supplied_not_ird_verified', $client->registry_sources['ird']);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('conflict_declarations', [
            'client_id' => $client->id,
            'advisor_id' => $advisor->id,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.created']);
        $this->assertDatabaseHas('audit_events', ['action' => 'conflict.declared']);
    }

    public function test_client_screen_collaboration_contract_exposes_only_client_participants(): void
    {
        $this->seed(RoleSeeder::class);
        config()->set('co-browse.enabled', true);
        $advisor = $this->advisor();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Client Screen Contract Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
        ]);
        $participant = User::factory()->withTwoFactor()->create([
            'name' => 'Client Screen Participant',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $participant->getKey(),
            'role' => 'primary_contact',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('screenShare.connection_url', route('advisor.clients.screen-share.connections.store', $client, absolute: false))
                ->where('screenShare.signal_url', route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false))
                ->where('screenShare.participants.0', [
                    'id' => (string) $participant->getKey(),
                    'name' => 'Client Screen Participant',
                ])
                ->where('coBrowse.action_url', route('co-browse.sessions.actions.store', ['session' => '__session__'], absolute: false))
                ->where('coBrowse.participants.0.id', (string) $participant->getKey()));
    }

    public function test_client_workspace_activity_payload_contract_is_shaped_at_the_boundary(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Client Workspace Contract Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
        ]);
        KnowledgeAssessment::query()->create([
            'client_id' => $client->getKey(),
            'financial_literacy' => 2,
            'strategic_awareness' => 3,
            'leadership' => 4,
            'calibration' => [
                'source' => 'knowledge_assessment',
                'language_depth' => 'plain_language',
                'financial_detail' => 'explain_terms',
                'strategic_framing' => 'balanced',
                'leadership_context' => 'delegate_to_leadership_team',
                'advisor_review_note' => 'Use clear language.',
                'scores' => [
                    'financial_literacy' => 2,
                    'strategic_awareness' => 3,
                    'leadership' => 4,
                ],
            ],
            'assessed_at' => now(),
            'assessed_by_user_id' => $advisor->getKey(),
        ]);
        $report = Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Client,
            'title' => 'Client workspace report',
            'generated_at' => now(),
            'metadata' => [],
        ]);
        $meeting = Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Client workspace meeting',
            'scheduled_at' => now()->addDay(),
            'attendees' => ['Advisor', 'Client owner'],
            'status' => Meeting::STATUS_SCHEDULED,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $briefing = IndustryBriefing::query()->create([
            'client_id' => $client->getKey(),
            'period' => now()->toDateString(),
            'body' => 'Industry context for the client workspace.',
            'sources' => [],
            'status' => IndustryBriefing::STATUS_DRAFT,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $preMeetingBrief = PreMeetingBrief::query()->create([
            'meeting_id' => $meeting->getKey(),
            'client_id' => $client->getKey(),
            'meeting_at' => $meeting->scheduled_at,
            'body' => 'Prepare the client workspace agenda.',
            'red_flag_ids' => [],
            'generated_at' => now(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.latest_knowledge_assessment.id', (string) $client->knowledgeAssessments()->firstOrFail()->getKey())
                ->where('client.latest_knowledge_assessment.calibration.scores.financial_literacy', 2)
                ->where('client.reports.0.id', (string) $report->getKey())
                ->where('client.reports.0.release_url', route('advisor.reports.release', $report, absolute: false))
                ->where('client.meetings.0.attendees', ['Advisor', 'Client owner'])
                ->where('client.industry_briefings.0.id', (string) $briefing->getKey())
                ->where('client.pre_meeting_briefs.0.id', (string) $preMeetingBrief->getKey()));
    }

    public function test_advisor_can_create_npo_governance_review_engagement_with_legal_structure(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engagementTypes', fn (Collection $types): bool => $types->contains(
                    'value',
                    EngagementType::NPO->value,
                ))
                ->where('npoOptions.legalStructures.0.value', NpoLegalStructure::RegisteredCharity->value));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.store'), [
                'engagement_type' => EngagementType::NPO->value,
                'nzbn' => '9429000000000',
                'legal_name' => '',
                'trading_name' => 'Future Shift Foundation',
                'entity_type' => '',
                'npo' => [
                    'sub_type' => NpoEngagementSubType::GovernanceReview->value,
                    'legal_structure' => NpoLegalStructure::RegisteredCharity->value,
                    'isa_2022_reregistered' => true,
                ],
                'conflict' => [
                    'declared' => true,
                    'referral_type' => 'client_creation',
                    'existing_relationship' => false,
                    'details' => null,
                ],
            ])
            ->assertRedirect();

        $client = Client::query()->firstOrFail();
        $engagement = NpoEngagement::query()->firstOrFail();

        $this->assertSame(EngagementType::NPO, $client->engagement_type);
        $this->assertSame((string) $client->getKey(), (string) $engagement->client_id);
        $this->assertSame(NpoEngagementSubType::GovernanceReview, $engagement->sub_type);
        $this->assertSame(NpoLegalStructure::RegisteredCharity, $engagement->legal_structure);
        $this->assertTrue($engagement->isa_2022_reregistered);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo_engagement.created',
            'subject_id' => $engagement->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.0.is_npo', true)
                ->where('clients.0.engagement_type_label', 'NPO'));
    }

    public function test_advisor_can_open_filtered_client_invite_form(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.invite', ['engagement_type' => EngagementType::NPO->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/clients/Invite')
                ->where('defaults.engagement_type', EngagementType::NPO->value)
                ->where('defaults.return_to', route('advisor.clients.index', ['engagement_type' => EngagementType::NPO->value], absolute: false))
                ->where('engagementTypes.0.value', EngagementType::STANDARD_ADVISORY->value)
                ->where('engagementTypes.1.value', EngagementType::DUE_DILIGENCE->value)
                ->where('engagementTypes.2.value', EngagementType::POST_ACQUISITION_ADVISORY->value)
                ->where('engagementTypes.3.value', EngagementType::NPO->value)
                ->has('engagementTypes', 4));
    }

    public function test_advisor_can_invite_due_diligence_client_from_filtered_path(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $returnTo = route('advisor.clients.index', ['engagement_type' => EngagementType::DUE_DILIGENCE->value], absolute: false);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => ' Buyer.Client@Example.com ',
                'engagement_type' => EngagementType::DUE_DILIGENCE->value,
                'return_to' => $returnTo,
            ])
            ->assertRedirect($returnTo)
            ->assertSessionHas('status', 'client-invited');

        $invite = InviteToken::query()->firstOrFail();

        $this->assertSame('buyer.client@example.com', $invite->email);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $invite->target_user_type);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $invite->target_role);
        $this->assertSame(ServiceActivation::SERVICE_DUE_DILIGENCE, $invite->intended_service_type);
        $this->assertNotEmpty($invite->token_envelope);
        $client = Client::query()->firstOrFail();
        $this->assertSame(EngagementType::DUE_DILIGENCE, $client->engagement_type);
        $this->assertSame('buyer.client@example.com', $client->registry_sources['invite_email']);
        $this->assertSame((string) $invite->getKey(), (string) $client->registry_sources['invite_token_id']);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_issued']);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_advisor_can_invite_post_acquisition_advisory_client(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $returnTo = route('advisor.clients.index', ['engagement_type' => EngagementType::POST_ACQUISITION_ADVISORY->value], absolute: false);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'settled.buyer@example.com',
                'engagement_type' => EngagementType::POST_ACQUISITION_ADVISORY->value,
                'return_to' => $returnTo,
            ])
            ->assertRedirect($returnTo)
            ->assertSessionHas('status', 'client-invited');

        $invite = InviteToken::query()->firstOrFail();
        $client = Client::query()->firstOrFail();

        $this->assertSame('settled.buyer@example.com', $invite->email);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $invite->target_user_type);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $invite->target_role);
        $this->assertNull($invite->intended_service_type);
        $this->assertSame(EngagementType::POST_ACQUISITION_ADVISORY, $client->engagement_type);
        $this->assertSame(EngagementType::POST_ACQUISITION_ADVISORY->value, $client->registry_sources['invite_engagement_type']);
        $this->assertSame('settled.buyer@example.com', $client->registry_sources['invite_email']);
        $this->assertSame((string) $invite->getKey(), (string) $client->registry_sources['invite_token_id']);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_issued']);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_accepting_advisor_client_invite_links_pending_workspace(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'owner@example.com',
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'return_to' => route('advisor.clients.index', absolute: false),
            ])
            ->assertRedirect(route('advisor.clients.index', absolute: false));

        $invite = InviteToken::query()->firstOrFail();
        $client = Client::query()->firstOrFail();
        $plainToken = Crypt::decryptString((string) $invite->token_envelope);

        auth()->guard()->logout();
        $this->flushSession();

        $this->post(route('invite.store', $plainToken), [
            'name' => 'Owner Person',
            'mobile_phone' => '+64 21 123 4567',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $client->refresh();

        $this->assertAuthenticatedAs($user);
        $this->assertSame((string) $user->getKey(), (string) $client->primary_contact_user_id);
        $this->assertSame('Owner Person', $client->legal_name);
        $this->assertContains((string) $client->getKey(), $user->accessibleClientIds());
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'role' => 'primary_contact',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_accepted']);
        $this->assertDatabaseHas('audit_events', ['action' => 'invite.accepted']);
    }

    public function test_pending_client_invite_is_labelled_awaiting_activation_and_can_be_resent_or_cancelled(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'pending.owner@example.com',
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'return_to' => route('advisor.clients.index', absolute: false),
            ])
            ->assertRedirect(route('advisor.clients.index', absolute: false));

        $client = Client::query()->firstOrFail();
        $originalInvite = InviteToken::query()->firstOrFail();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.0.id', $client->id)
                ->where('clients.0.status', 'active')
                ->where('clients.0.account_status', 'awaiting_activation')
                ->where('clients.0.account_status_label', 'Awaiting activation'));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('client.invitation.status', 'awaiting_activation')
                ->where('client.invitation.email', 'pending.owner@example.com')
                ->where('client.invitation.resend_url', route('advisor.clients.invite.resend', $client, absolute: false))
                ->where('client.invitation.cancel_url', route('advisor.clients.invite.cancel', $client, absolute: false)));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.resend', $client))
            ->assertRedirect(route('advisor.clients.show', $client))
            ->assertSessionHas('status', 'client-invite-resent');

        $resentInvite = InviteToken::query()
            ->whereKeyNot($originalInvite->getKey())
            ->firstOrFail();

        $this->assertTrue($originalInvite->refresh()->isExpired());
        $this->assertSame((string) $resentInvite->getKey(), (string) $client->refresh()->registry_sources['invite_token_id']);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_resent']);
        Mail::assertSent(InvitationMail::class, 2);

        $this->actingAsMfa($advisor)
            ->delete(route('advisor.clients.invite.cancel', $client))
            ->assertRedirect(route('advisor.clients.show', $client))
            ->assertSessionHas('status', 'client-invite-cancelled');

        $this->assertTrue($resentInvite->refresh()->isExpired());
        $this->assertNotNull($client->refresh()->registry_sources['invite_cancelled_at'] ?? null);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_cancelled']);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('client.account_status', 'invite_cancelled')
                ->where('client.account_status_label', 'Invite cancelled')
                ->where('client.invitation.cancel_url', null)
                ->where('client.invitation.resend_url', route('advisor.clients.invite.resend', $client, absolute: false)));
    }

    public function test_expired_unaccepted_client_invite_can_still_be_cancelled(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'expired.pending.owner@example.com',
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'return_to' => route('advisor.clients.index', absolute: false),
            ])
            ->assertRedirect(route('advisor.clients.index', absolute: false));

        $client = Client::query()->firstOrFail();
        $invite = InviteToken::query()->firstOrFail();
        $invite->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('client.account_status', 'awaiting_activation')
                ->where('client.invitation.resend_url', route('advisor.clients.invite.resend', $client, absolute: false))
                ->where('client.invitation.cancel_url', route('advisor.clients.invite.cancel', $client, absolute: false)));

        $this->actingAsMfa($advisor)
            ->delete(route('advisor.clients.invite.cancel', $client))
            ->assertRedirect(route('advisor.clients.show', $client))
            ->assertSessionHas('status', 'client-invite-cancelled');

        $this->assertNotNull($client->refresh()->registry_sources['invite_cancelled_at'] ?? null);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_cancelled']);
    }

    public function test_client_portal_reconciles_an_accepted_replacement_invite_with_its_workspace(): void
    {
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $advisor = $this->advisor();
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Jim',
            'email' => 'jim@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        $expiredInvite = InviteToken::query()->create([
            'email' => $user->email,
            'target_role' => User::TYPE_CLIENT_PRIMARY,
            'target_user_type' => User::TYPE_CLIENT_PRIMARY,
            'token_hash' => InviteToken::hashToken('expired-client-invite'),
            'expires_at' => now()->subHour(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);
        $acceptedInvite = InviteToken::query()->create([
            'email' => $user->email,
            'target_role' => User::TYPE_CLIENT_PRIMARY,
            'target_user_type' => User::TYPE_CLIENT_PRIMARY,
            'token_hash' => InviteToken::hashToken('accepted-replacement-client-invite'),
            'expires_at' => now()->addHour(),
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->getKey(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'legal_name' => 'Invited client - '.$user->email,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'registry_sources' => [
                'source' => 'advisor_client_invite',
                'invite_token_id' => $expiredInvite->getKey(),
                'invite_email' => $user->email,
            ],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clients.0.id', $client->getKey())
                ->where('clients.0.account_status', 'active')
                ->where('clients.0.account_status_label', 'Active'));

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->actingAsMfa($user)
            ->get(route('portal.messages.index'))
            ->assertOk();

        $client->refresh();
        $this->assertSame((string) $user->getKey(), (string) $client->primary_contact_user_id);
        $this->assertSame('Jim', $client->legal_name);
        $this->assertSame((string) $acceptedInvite->getKey(), (string) $client->registry_sources['invite_token_id']);
        $this->assertContains((string) $client->getKey(), $user->accessibleClientIds());
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.invite_reconciled']);
    }

    public function test_client_portal_repairs_missing_team_membership_for_an_explicit_primary_contact(): void
    {
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Jim',
            'email' => 'jim.primary@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'legal_name' => 'Jim',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
            'created_by_user_id' => $user->getKey(),
        ]);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->assertContains((string) $client->getKey(), $user->accessibleClientIds());
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'client.primary_contact_reconciled']);
    }

    public function test_npo_client_invite_prepares_governance_review_workspace(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'npo.owner@example.com',
                'engagement_type' => EngagementType::NPO->value,
                'return_to' => route('advisor.clients.index', ['engagement_type' => EngagementType::NPO->value], absolute: false),
            ])
            ->assertRedirect(route('advisor.clients.index', ['engagement_type' => EngagementType::NPO->value], absolute: false));

        $client = Client::query()->firstOrFail();
        $engagement = NpoEngagement::query()->firstOrFail();

        $this->assertSame(EngagementType::NPO, $client->engagement_type);
        $this->assertSame((string) $client->getKey(), (string) $engagement->client_id);
        $this->assertSame(NpoEngagementSubType::GovernanceReview, $engagement->sub_type);
        $this->assertSame(NpoLegalStructure::UnincorporatedCommunityOrganisation, $engagement->legal_structure);
        $this->assertDatabaseHas('audit_events', ['action' => 'npo_engagement.created']);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_conflict_declaration_is_required_before_client_save(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.store'), [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => '9429000000000',
                'conflict' => [
                    'declared' => false,
                    'referral_type' => 'client_creation',
                    'existing_relationship' => false,
                    'details' => null,
                ],
            ])
            ->assertSessionHasErrors('conflict.declared');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('conflict_declarations', 0);
    }

    public function test_advisor_cannot_create_or_invite_clients_over_their_admin_set_capacity(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $advisor->forceFill(['advisor_client_capacity_limit' => 1])->save();
        $this->clientForAdvisor($advisor, 'Existing Client Limited', EngagementType::STANDARD_ADVISORY);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.store'), [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => '9429000000000',
                'conflict' => [
                    'declared' => true,
                    'referral_type' => 'client_creation',
                    'existing_relationship' => false,
                    'details' => null,
                ],
            ])
            ->assertSessionHasErrors('capacity');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.invite.store'), [
                'email' => 'over-capacity@example.com',
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'return_to' => route('advisor.clients.index', absolute: false),
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('invite_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_clients_index_can_filter_by_engagement_type_for_sidebar_shortcuts(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $standard = $this->clientForAdvisor($advisor, 'Standard Limited', EngagementType::STANDARD_ADVISORY);
        $dueDiligence = $this->clientForAdvisor($advisor, 'Target Due Diligence Limited', EngagementType::DUE_DILIGENCE);
        $npo = $this->clientForAdvisor($advisor, 'Community Impact Trust', EngagementType::NPO);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['engagement_type' => EngagementType::STANDARD_ADVISORY->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engagementFilter.key', EngagementType::STANDARD_ADVISORY->value)
                ->where('engagementFilter.label', 'Advisory')
                ->where('clients.0.id', $standard->id)
                ->has('clients', 1));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['engagement_type' => EngagementType::DUE_DILIGENCE->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engagementFilter.key', EngagementType::DUE_DILIGENCE->value)
                ->where('engagementFilter.label', 'Due Diligence')
                ->where('clients.0.id', $dueDiligence->id)
                ->has('clients', 1));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['engagement_type' => EngagementType::NPO->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engagementFilter.key', EngagementType::NPO->value)
                ->where('engagementFilter.label', 'NPOs')
                ->where('clients.0.id', $npo->id)
                ->has('clients', 1));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['engagement_type' => 'not-real']))
            ->assertNotFound();

        $this->assertDatabaseHas('clients', ['id' => $standard->id]);
    }

    public function test_client_index_resource_contract_does_not_expose_registry_payloads(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->clientForAdvisor($advisor, 'Typed Contract Limited', EngagementType::STANDARD_ADVISORY);
        $client->forceFill([
            'registry_sources' => [
                'source' => 'advisor_client_invite',
                'invite_email' => 'contract@example.test',
                'invite_token_id' => '00000000-0000-4000-8000-000000000099',
                'internal_only' => 'must-not-cross-the-boundary',
            ],
        ])->save();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('clients.0.id', (string) $client->getKey())
                ->where('clients.0.engagement_type_label', 'Standard Advisory')
                ->where('clients.0.data_quality', Client::DATA_QUALITY_INSUFFICIENT)
                ->where('clients.0.account_status', 'awaiting_activation')
                ->missing('clients.0.registry_sources')
                ->missing('clients.0.invite_token_id'));
    }

    public function test_junior_advisor_cannot_create_clients(): void
    {
        $this->seed(RoleSeeder::class);
        $junior = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_JUNIOR_ADVISOR,
            'primary_role' => User::TYPE_JUNIOR_ADVISOR,
        ]);
        $junior->assignRole(User::TYPE_JUNIOR_ADVISOR);

        $this->actingAsMfa($junior)
            ->get(route('advisor.clients.create'))
            ->assertForbidden();
    }

    public function test_engagement_type_reports_locked_once_questionnaire_responses_exist(): void
    {
        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'Future Shift Advisory Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);

        $this->assertFalse($client->engagementTypeIsLocked());

        $this->assertFalse($client->engagementTypeIsLocked());

        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => '1',
            'title' => 'Standard Advisory Questionnaire',
            'published_at' => now(),
        ]);

        DB::table('questionnaire_responses')->insert([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($client->engagementTypeIsLocked());
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientForAdvisor(User $advisor, string $name, EngagementType $type): Client
    {
        $client = Client::query()->create([
            'engagement_type' => $type->value,
            'nzbn' => '9429000000000',
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [$type->value],
        ]);

        return $client;
    }
}
