<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireSet;
use App\Mail\InvitationMail;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\InviteToken;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_advisor_can_create_npo_governance_review_engagement_with_legal_structure(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('engagementTypes.4.value', EngagementType::NPO->value)
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
                ->where('engagementTypes.2.value', EngagementType::NPO->value)
                ->has('engagementTypes', 3));
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
