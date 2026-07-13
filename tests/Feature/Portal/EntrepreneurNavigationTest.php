<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EntrepreneurStage;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\IdeaValidationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class EntrepreneurNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrepreneur_navigation_targets_are_available(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Tania Hassounia',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Portal navigation test profile.',
        ]);

        foreach ($this->entrepreneurUrls() as $url) {
            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_entrepreneur_navigation_recovers_accepted_invite_profile_before_loading_targets(): void
    {
        $this->seed(RoleSeeder::class);

        foreach ($this->entrepreneurUrls() as $index => $url) {
            $advisor = User::factory()->create([
                'user_type' => User::TYPE_ADVISOR,
                'primary_role' => User::TYPE_ADVISOR,
            ]);
            $entrepreneur = User::factory()->withTwoFactor()->create([
                'email' => "accepted-nav-{$index}@example.test",
                'user_type' => User::TYPE_ENTREPRENEUR,
                'primary_role' => User::TYPE_ENTREPRENEUR,
            ]);
            $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
            $invite = InviteToken::query()->create([
                'email' => $entrepreneur->email,
                'target_role' => User::TYPE_ENTREPRENEUR,
                'target_user_type' => User::TYPE_ENTREPRENEUR,
                'token_hash' => InviteToken::hashToken("accepted-nav-token-{$index}"),
                'expires_at' => now()->addDays(5),
                'accepted_at' => now(),
                'accepted_by_user_id' => $entrepreneur->getKey(),
            ]);
            $profile = EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $invite->getKey(),
                'name' => "Accepted Nav {$index}",
                'email' => strtoupper($entrepreneur->email),
                'stage' => EntrepreneurStage::INVITED,
                'concept_summary' => 'Accepted invite should reconcile before portal navigation renders.',
            ]);

            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();

            $profile->refresh();
            $this->assertSame((string) $entrepreneur->getKey(), (string) $profile->user_id);
            $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        }
    }

    public function test_entrepreneur_navigation_repairs_legacy_accepted_invite_without_accepted_user(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'legacy-accepted-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $invite = InviteToken::query()->create([
            'email' => $entrepreneur->email,
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'token_hash' => InviteToken::hashToken('legacy-accepted-nav-token'),
            'expires_at' => now()->addDays(5),
            'accepted_at' => now(),
            'accepted_by_user_id' => null,
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => $invite->getKey(),
            'name' => 'Legacy Accepted Founder',
            'email' => strtoupper($entrepreneur->email),
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Accepted invite predates accepted_by_user_id capture.',
        ]);

        foreach ($this->entrepreneurUrls() as $url) {
            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();
        }

        $profile->refresh();
        $invite->refresh();

        $this->assertSame((string) $entrepreneur->getKey(), (string) $profile->user_id);
        $this->assertSame((string) $entrepreneur->getKey(), (string) $invite->accepted_by_user_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
    }

    public function test_entrepreneur_navigation_creates_missing_profile_from_accepted_invite(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'missing-profile-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $invite = InviteToken::query()->create([
            'email' => strtoupper($entrepreneur->email),
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'token_hash' => InviteToken::hashToken('missing-profile-nav-token'),
            'expires_at' => now()->addDays(5),
            'accepted_at' => now(),
            'accepted_by_user_id' => $entrepreneur->getKey(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);

        foreach ($this->entrepreneurUrls() as $url) {
            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();
        }

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $entrepreneur->getKey())
            ->firstOrFail();

        $this->assertSame((string) $advisor->getKey(), (string) $profile->assigned_advisor_id);
        $this->assertSame((string) $invite->getKey(), (string) $profile->invite_token_id);
        $this->assertSame($entrepreneur->email, $profile->email);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
    }

    public function test_business_idea_invite_limits_direct_entrepreneur_access_to_idea_validation(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'idea-only-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $invite = InviteToken::query()->create([
            'email' => $entrepreneur->email,
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'token_hash' => InviteToken::hashToken('idea-only-token'),
            'expires_at' => now()->addDays(5),
            'accepted_at' => now(),
            'accepted_by_user_id' => $entrepreneur->getKey(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);
        EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => $invite->getKey(),
            'name' => 'Idea Only Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Advisor selected the Business Idea invite path.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('packageAccess.package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)
                ->where('packageAccess.includes_idea_validation', true)
                ->where('packageAccess.includes_plan_budget', false));

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.plan.start'))
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('entrepreneur_plan_error', 'Business plan and budget are not included in your selected package.');
    }

    public function test_idea_validation_submission_surfaces_advisor_review_queue(): void
    {
        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);

        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'queue-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $invite = InviteToken::query()->create([
            'email' => $entrepreneur->email,
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'token_hash' => InviteToken::hashToken('queue-idea-token'),
            'expires_at' => now()->addDays(5),
            'accepted_at' => now(),
            'accepted_by_user_id' => $entrepreneur->getKey(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => $invite->getKey(),
            'name' => 'Queue Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Founder is testing a Business Idea package submission.',
        ]);
        $payload = [
            'problem' => 'Business Advisory',
            'target_customer' => "SME's",
            'solution' => 'System that can evaluate their current state, analyse, develop a strategic plan and track implementation thereof.',
            'value_proposition' => 'It supports them in growing/improving there business and ultimately increasing the business cash flow position.',
            'demand_signal' => "Struggling SME's",
            'revenue_model' => 'Service fee for advisory support',
        ];

        $atLimitPayload = array_map(
            static fn (string $value): string => str_pad($value, 5000, 'x'),
            $payload,
        );
        $tooLongPayload = array_map(
            static fn (string $value): string => str_pad($value, 5001, 'x'),
            $payload,
        );

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.store'), $tooLongPayload)
            ->assertSessionHasErrors(array_keys($tooLongPayload));

        $payload = $atLimitPayload;

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.store'), $payload)
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-submitted');

        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->firstOrFail();

        $this->assertNull($validation->advisor_gate_passed_at);
        $this->assertSame(1, $validation->revision_number);
        $this->assertNull($validation->previous_validation_id);
        $this->assertSame($payload['problem'], $validation->problem);
        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->refresh()->stage);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ideaValidation.id', $validation->id)
                ->where('ideaValidation.revision_number', 1)
                ->where('ideaValidation.plan_builder_unlocked', false)
                ->where('ideaValidation.problem', $payload['problem']));

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('advisor/Dashboard')
                ->where('entrepreneurReviews.summary.idea_validations', 1)
                ->where('entrepreneurReviews.items.0.id', $validation->id)
                ->where('entrepreneurReviews.items.0.type', 'idea_validation')
                ->where('entrepreneurReviews.items.0.entrepreneur_name', 'Queue Founder')
                ->where('entrepreneurReviews.items.0.action_label', 'Review idea'));

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.recall'))
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-recalled');

        $validation->refresh();
        $this->assertNotNull($validation->recalled_at);
        $this->assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->refresh()->stage);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ideaValidation.id', $validation->id)
                ->where('ideaValidation.advisor_gate_status', 'recalled')
                ->where('ideaValidation.recalled_at', $validation->recalled_at?->toIso8601String())
                ->where('ideaValidation.problem', $payload['problem']));

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entrepreneurReviews.summary.idea_validations', 0));

        $recalledPayload = array_merge($payload, [
            'demand_signal' => 'Three SME owners completed interviews and one agreed to a paid discovery pilot.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.store'), $recalledPayload)
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-submitted');

        $resubmittedValidation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('evaluated_at')
            ->latest()
            ->firstOrFail();

        $this->assertNotSame($validation->id, $resubmittedValidation->id);
        $this->assertSame(2, $resubmittedValidation->revision_number);
        $this->assertSame($validation->id, $resubmittedValidation->previous_validation_id);
        $this->assertSame('advisor_review', data_get($resubmittedValidation->ai_evaluation, 'metadata.advisor_gate_status', 'advisor_review'));
        $this->assertSame($recalledPayload['demand_signal'], $resubmittedValidation->demand_signal);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entrepreneurReviews.summary.idea_validations', 1)
                ->where('entrepreneurReviews.items.0.id', $resubmittedValidation->id)
                ->where('entrepreneurReviews.items.0.action_label', 'Review idea'));

        Notification::fake();
        $changeNote = 'Please interview one more owner, record the hypothesis, evidence, result, and next step, then resubmit.';
        app(IdeaValidationService::class)->requestChanges($resubmittedValidation->refresh(), $advisor, $changeNote);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ideaValidation.id', $resubmittedValidation->id)
                ->where('ideaValidation.advisor_gate_status', 'changes_requested')
                ->where('ideaValidation.change_request_note', $changeNote)
                ->where('ideaValidation.plan_builder_unlocked', false));

        $revisedPayload = array_merge($recalledPayload, [
            'demand_signal' => 'Five SME owners completed interviews and two agreed to a paid discovery pilot.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.store'), $revisedPayload)
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-submitted');

        $latestValidation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('evaluated_at')
            ->latest()
            ->firstOrFail();

        $this->assertNotSame($resubmittedValidation->id, $latestValidation->id);
        $this->assertSame(3, $latestValidation->revision_number);
        $this->assertSame($resubmittedValidation->id, $latestValidation->previous_validation_id);
        $this->assertSame('advisor_review', data_get($latestValidation->ai_evaluation, 'metadata.advisor_gate_status', 'advisor_review'));
        $this->assertSame($revisedPayload['demand_signal'], $latestValidation->demand_signal);
        $this->assertNotNull($resubmittedValidation->refresh()->recalled_at);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.idea-validation.restore', $validation))
            ->assertRedirect(route('portal.entrepreneur.plan.show', absolute: false))
            ->assertSessionHas('status', 'entrepreneur-idea-restored');

        $restoredValidation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->firstOrFail();

        $this->assertSame(4, $restoredValidation->revision_number);
        $this->assertSame($latestValidation->id, $restoredValidation->previous_validation_id);
        $this->assertSame($payload['problem'], $restoredValidation->problem);
        $this->assertSame($payload['demand_signal'], $restoredValidation->demand_signal);
        $this->assertSame($validation->id, data_get($restoredValidation->ai_evaluation, 'metadata.restored_from_validation_id'));
        $this->assertSame(1, data_get($restoredValidation->ai_evaluation, 'metadata.restored_from_revision_number'));
        $this->assertNotNull($latestValidation->refresh()->recalled_at);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entrepreneurReviews.summary.idea_validations', 1)
                ->where('entrepreneurReviews.items.0.id', $restoredValidation->id)
                ->where('entrepreneurReviews.items.0.action_label', 'Review idea'));
    }

    public function test_entrepreneur_can_start_buying_business_service_from_portal(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'buyer-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Buyer Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Founder also wants to buy a business.',
        ]);
        $package = $this->dueDiligencePackage();

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('buyingBusinessServiceUrl', route('portal.service-activations.create', ['serviceType' => ServiceActivation::SERVICE_DUE_DILIGENCE], absolute: false)));

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.service-activations.create', ['serviceType' => ServiceActivation::SERVICE_DUE_DILIGENCE]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('portal/ServiceActivationRequest')
                ->where('service.service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('pricingPreview.status', 'needs_purchase_price')
                ->where('pricingPreview.packages.0.id', (string) $package->getKey())
                ->where('pricingPreview.packages.0.fixed_fee', 8500)
                ->where('dashboardUrl', route('portal.entrepreneur.dashboard', absolute: false)));

        $profile->refresh();

        $this->assertNotNull($profile->client_id);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $profile->client_id,
            'user_id' => $entrepreneur->getKey(),
            'role' => 'primary_contact',
        ]);
        $this->assertTrue(ClientTeamMember::query()
            ->where('client_id', $profile->client_id)
            ->where('user_id', $advisor->getKey())
            ->where('role', 'lead_advisor')
            ->exists());

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.service-activations.store'), [
                'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
                'target_name' => 'Kauri Kitchens Group Limited',
                'vendor_name' => 'Kauri Vendors',
                'industry' => 'Food service',
                'asking_price' => 850000,
                'timing' => 'Shortlisting now',
                'notes' => 'I want due diligence support before submitting an offer.',
                'pricing_acknowledged' => true,
                'pricing_package_id' => $package->getKey(),
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $profile->client_id)
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->firstOrFail();

        $this->assertSame(ServiceActivation::STATUS_REQUESTED, $activation->status);
        $this->assertSame((string) $advisor->getKey(), (string) $activation->advisor_id);
        $this->assertSame('Kauri Kitchens Group Limited', $activation->intake['target_name']);
        $this->assertSame('matched_package', $activation->metadata['pre_request_pricing']['status']);
        $this->assertSame(8500.0, $activation->metadata['pre_request_pricing']['package']['fixed_fee']);
        $this->assertSame((string) $package->getKey(), (string) $activation->metadata['pre_request_pricing']['package']['id']);
    }

    public function test_buying_business_request_survives_notification_delivery_failure(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'buyer-notification-failure@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Buyer Notification Failure',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Founder wants due diligence support.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.service-activations.create', ['serviceType' => ServiceActivation::SERVICE_DUE_DILIGENCE]))
            ->assertOk();

        $profile->refresh();

        Notification::shouldReceive('send')
            ->atLeast()
            ->once()
            ->andThrow(new RuntimeException('Notification transport unavailable.'));

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.service-activations.store'), [
                'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
                'target_name' => 'Kauri Kitchens Group Limited',
                'vendor_name' => 'Kauri Vendors',
                'industry' => 'Food service',
                'asking_price' => 850000,
                'timing' => 'Shortlisting now',
                'notes' => 'I want due diligence support before submitting an offer.',
                'pricing_acknowledged' => true,
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $profile->client_id)
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->firstOrFail();

        $this->assertSame(ServiceActivation::STATUS_REQUESTED, $activation->status);
        $this->assertSame((string) $advisor->getKey(), (string) $activation->advisor_id);
        $this->assertSame('Kauri Kitchens Group Limited', $activation->intake['target_name']);
        $this->assertNotNull($activation->client_message_thread_id);
    }

    public function test_entrepreneur_navigation_reclaims_same_email_profile_from_stale_user(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $staleUser = User::factory()->create([
            'email' => 'stale-linked-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'reclaimed-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $invite = InviteToken::query()->create([
            'email' => '  '.strtoupper($entrepreneur->email).'  ',
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'token_hash' => InviteToken::hashToken('reclaimed-profile-nav-token'),
            'expires_at' => now()->addDays(5),
            'accepted_at' => now(),
            'accepted_by_user_id' => $entrepreneur->getKey(),
            'issued_by_user_id' => $advisor->getKey(),
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $staleUser->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'invite_token_id' => $invite->getKey(),
            'name' => 'Reclaimed Founder',
            'email' => '  '.strtoupper($entrepreneur->email).'  ',
            'stage' => EntrepreneurStage::CANCELLED,
            'concept_summary' => 'Profile is linked to a stale user and should be reclaimed.',
        ]);

        foreach ($this->entrepreneurUrls() as $url) {
            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();
        }

        $profile->refresh();

        $this->assertSame((string) $entrepreneur->getKey(), (string) $profile->user_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
    }

    /**
     * @return array<int, string>
     */
    private function entrepreneurUrls(): array
    {
        return [
            route('portal.entrepreneur.dashboard', absolute: false),
            route('portal.entrepreneur.plan.show', absolute: false),
            route('portal.calendar.index', absolute: false),
            route('portal.inspiration-board.index', absolute: false),
            route('portal.entrepreneur.surveys.index', absolute: false),
            route('portal.messages.index', absolute: false),
            route('notifications.index', absolute: false),
        ];
    }

    private function dueDiligencePackage(): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
            'package_scope' => ServiceRatePackage::SCOPE_DD_300K_1M,
            'package_name' => 'Purchase price between $300k and $1m',
            'client_label' => 'Purchase price between $300k and $1m',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 8500,
            'deposit_percent' => 50,
            'currency' => 'NZD',
            'purchase_price_min' => 300001,
            'purchase_price_max' => 1000000,
            'scope_description' => 'Business purchase price between $300k and $1m.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);
    }
}
