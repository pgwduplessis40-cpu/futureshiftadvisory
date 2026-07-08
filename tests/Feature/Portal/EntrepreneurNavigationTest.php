<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EntrepreneurStage;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ])
            ->assertRedirect();

        $activation = ServiceActivation::query()
            ->where('client_id', $profile->client_id)
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->firstOrFail();

        $this->assertSame(ServiceActivation::STATUS_REQUESTED, $activation->status);
        $this->assertSame((string) $advisor->getKey(), (string) $activation->advisor_id);
        $this->assertSame('Kauri Kitchens Group Limited', $activation->intake['target_name']);
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
}
