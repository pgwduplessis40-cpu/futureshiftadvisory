<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Portal\EntrepreneurPlanWorkspace;
use App\Models\AuditEvent;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\PilotFeeWaiverProgram;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteOffer;
use App\Services\Fees\PilotFeeWaiverManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EntrepreneurInviteOfferTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, bool, bool}> */
    public static function scopes(): array
    {
        return [
            'idea only' => ['idea_validation', true, false],
            'plan and budget only' => ['plan_budget', false, true],
            'both' => ['combo', true, true],
        ];
    }

    #[DataProvider('scopes')]
    public function test_invite_preserves_offer_through_resend_and_requires_onboarding_acceptance(string $scope, bool $idea, bool $plan): void
    {
        [$advisor, $profile, $package] = $this->issue($scope);
        $original = $profile->inviteToken->service_offer_snapshot;
        $originalStage = $profile->stage;
        $package->update(['fixed_fee' => 9900, 'scope_description' => 'Changed terms after the invitation.']);

        $this->actingAsMfa($advisor)->post(route('advisor.entrepreneurs.invite.resend', $profile))->assertRedirect();
        $invite = $profile->refresh()->inviteToken;
        $this->assertEquals($original, $invite->service_offer_snapshot);

        $this->app['auth']->forgetGuards();
        $this->get(route('invite.accept', Crypt::decryptString($invite->token_envelope)))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('serviceOffers.0.fee', 2900));
        $this->post(route('invite.store', Crypt::decryptString($invite->token_envelope)), [
            'name' => 'Test Founder', 'mobile_phone' => '+64211234567',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('mfa.setup'));
        $user = User::query()->where('email', $profile->email)->firstOrFail();

        $this->actingAsMfa($user)->get(route('portal.entrepreneur.dashboard'))
            ->assertRedirect(route('portal.entrepreneur.service-offer.show'));
        $this->post(route('portal.entrepreneur.plan.start'))
            ->assertRedirect(route('portal.entrepreneur.service-offer.show'));
        $this->assertDatabaseCount('business_plans', 0);
        $response = $this->get(route('portal.entrepreneur.service-offer.show'));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('portal/entrepreneur/ServiceOffer')
            ->where('offer.fixed_fee', 2900)
            ->where('offer.package_scope', $scope)
            ->where('offer.scope_description', 'The originally selected scope.'));
        $version = $response->inertiaProps('offerVersion');
        $this->post(route('portal.entrepreneur.service-offer.store'), ['offer_version' => $version])
            ->assertSessionHasErrors('accepted');
        $this->assertNull($invite->refresh()->service_offer_accepted_at);
        $this->post(route('portal.entrepreneur.service-offer.store'), ['accepted' => true, 'offer_version' => $version])
            ->assertRedirect(route('portal.entrepreneur.dashboard'));
        $this->post(route('portal.entrepreneur.service-offer.store'), ['accepted' => true, 'offer_version' => $version])
            ->assertRedirect();
        $this->assertSame($user->getKey(), $invite->refresh()->service_offer_accepted_by_user_id);
        $this->assertEquals(2900, $invite->service_offer_accepted_snapshot['fixed_fee']);
        $this->assertDatabaseCount('service_activations', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.invite_offer_accepted', 'subject_id' => $invite->getKey()]);
        $this->assertSame(1, AuditEvent::query()->where('action', 'entrepreneur.invite_offer_accepted')->count());
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->refresh()->stage);
        $access = app(EntrepreneurPlanWorkspace::class)->packageAccess($profile);
        $this->assertSame($idea, $access['includes_idea_validation']);
        $this->assertSame($plan, $access['includes_plan_budget']);
        $this->assertSame(EntrepreneurStage::INVITED, $originalStage);
        $this->get(route('portal.entrepreneur.plan.show'))->assertOk();
    }

    public function test_changed_rate_and_unavailable_scope_cannot_be_sent_as_the_old_offer(): void
    {
        [$advisor, , $package] = $this->issue('plan_budget');
        $version = EntrepreneurInviteOffer::version($package->snapshot());
        $package->update(['fixed_fee' => 3400]);
        $data = ['name' => 'Another Founder', 'email' => 'another@example.test', 'intended_package_scope' => 'plan_budget', 'service_offer_version' => $version];
        $this->actingAsMfa($advisor)->post(route('advisor.entrepreneurs.store'), $data)
            ->assertSessionHasErrors('intended_package_scope');
        $package->update(['is_active' => false]);
        unset($data['service_offer_version']);
        $this->post(route('advisor.entrepreneurs.store'), $data)->assertSessionHasErrors('intended_package_scope');
        $this->assertDatabaseCount('invite_tokens', 1);
    }

    public function test_changing_invited_scope_uses_a_new_offer_when_resent(): void
    {
        [$advisor, $profile] = $this->issue('idea_validation');
        $originalInvite = $profile->inviteToken;
        $this->package('plan_budget', 3600);
        $this->actingAsMfa($advisor)->patch(route('advisor.entrepreneurs.invite.update', $profile), [
            'name' => $profile->name, 'email' => $profile->email, 'intended_package_scope' => 'plan_budget',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('advisor.entrepreneurs.invite.resend', $profile))->assertRedirect()->assertSessionHasNoErrors();
        $newInvite = $profile->refresh()->inviteToken;
        $this->assertSame('plan_budget', $newInvite->service_offer_snapshot['package_scope']);
        $this->assertEquals(3600, $newInvite->service_offer_snapshot['fixed_fee']);
        $this->assertTrue($originalInvite->refresh()->isExpired());
        $this->assertSame('idea_validation', $originalInvite->service_offer_snapshot['package_scope']);
    }

    public function test_fee_waiver_is_disclosed_and_stale_consent_is_rejected(): void
    {
        [$advisor, $profile] = $this->issue('combo');
        $user = $this->linkUser($profile);
        $waivers = app(PilotFeeWaiverManager::class);
        $waivers->updateProgram(PilotFeeWaiverProgram::STATUS_OPEN, $advisor);
        $waivers->updateEntrepreneur($profile, [
            'enabled' => true, 'starts_at' => null, 'expires_at' => now()->addDays(5)->toDateString(), 'reason' => 'Pilot test.',
        ], $advisor);
        $response = $this->actingAsMfa($user)->get(route('portal.entrepreneur.service-offer.show'));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('offer.fixed_fee', 0)->where('offer.pilot_fee_waiver.nominal_fixed_fee', 2900));
        $version = $response->inertiaProps('offerVersion');
        $waivers->updateProgram(PilotFeeWaiverProgram::STATUS_CLOSED, $advisor);
        $this->post(route('portal.entrepreneur.service-offer.store'), ['accepted' => true, 'offer_version' => $version])
            ->assertSessionHasErrors('accepted');
        $this->assertNull($profile->inviteToken->refresh()->service_offer_accepted_at);
        $waivers->updateProgram(PilotFeeWaiverProgram::STATUS_OPEN, $advisor);
        $this->post(route('portal.entrepreneur.service-offer.store'), ['accepted' => true, 'offer_version' => $version])->assertSessionHasNoErrors();
        $this->assertEquals(0, $profile->inviteToken->refresh()->service_offer_accepted_snapshot['fixed_fee']);
    }

    public function test_offer_acceptance_cannot_target_another_account(): void
    {
        [, $profile] = $this->issue('combo');
        $user = User::factory()->create(['user_type' => User::TYPE_ENTREPRENEUR, 'primary_role' => User::TYPE_ENTREPRENEUR]);
        $this->actingAsMfa($user)->post(route('portal.entrepreneur.service-offer.store'), [
            'accepted' => true, 'offer_version' => EntrepreneurInviteOffer::version($profile->inviteToken->service_offer_snapshot),
            'invite_token_id' => $profile->invite_token_id,
        ])->assertNotFound();
        $this->assertNull($profile->inviteToken->refresh()->service_offer_accepted_at);
    }

    public function test_admin_entrepreneur_invitation_also_requires_and_preserves_a_service_offer(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['user_type' => User::TYPE_SUPER_ADMIN, 'primary_role' => User::TYPE_SUPER_ADMIN]);
        $admin->assignRole(User::TYPE_SUPER_ADMIN);
        $package = $this->package('plan_budget');
        $data = ['email' => 'admin-invited@example.test', 'target_user_type' => User::TYPE_ENTREPRENEUR, 'target_role' => User::TYPE_ENTREPRENEUR];
        $this->actingAsMfa($admin)->post('/admin/invitations', $data)->assertSessionHasErrors('intended_package_scope');
        $this->post('/admin/invitations', [...$data, 'intended_package_scope' => 'plan_budget', 'service_offer_version' => EntrepreneurInviteOffer::version($package->snapshot())])
            ->assertRedirect()->assertSessionHasNoErrors();
        $invite = InviteToken::query()->where('email', $data['email'])->firstOrFail();
        $this->assertSame('plan_budget', $invite->intended_package_scope);
        $this->assertEquals(2900, $invite->service_offer_snapshot['fixed_fee']);
    }

    public function test_login_recovery_cannot_bypass_the_service_agreement(): void
    {
        [, $profile] = $this->issue('combo');
        $user = User::factory()->create(['email' => $profile->email, 'user_type' => User::TYPE_ENTREPRENEUR, 'primary_role' => User::TYPE_ENTREPRENEUR]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);
        $this->assertNull($profile->inviteToken->accepted_at);
        $this->actingAsMfa($user)->post(route('portal.entrepreneur.plan.start'))
            ->assertRedirect(route('portal.entrepreneur.service-offer.show'));
        $this->assertDatabaseCount('business_plans', 0);
        $this->assertNull($profile->inviteToken->refresh()->service_offer_accepted_at);
    }

    /** @return array{User, EntrepreneurProfile, ServiceRatePackage} */
    private function issue(string $scope): array
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = User::factory()->create(['user_type' => User::TYPE_ADVISOR, 'primary_role' => User::TYPE_ADVISOR]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $package = $this->package($scope);
        $this->actingAsMfa($advisor)->post(route('advisor.entrepreneurs.store'), [
            'name' => 'Test Founder', 'email' => 'founder@example.test', 'intended_package_scope' => $scope,
            'service_offer_version' => EntrepreneurInviteOffer::version($package->snapshot()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return [$advisor, EntrepreneurProfile::query()->with('inviteToken')->firstOrFail(), $package];
    }

    private function package(string $scope, int $fee = 2900): ServiceRatePackage
    {
        return ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR, 'package_scope' => $scope,
            'package_name' => 'Test service '.$scope, 'client_label' => 'Test service '.$scope,
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE, 'fixed_fee' => $fee,
            'currency' => 'NZD', 'scope_description' => 'The originally selected scope.',
            'is_active' => true, 'effective_from' => now()->subMinute(),
        ]);
    }

    private function linkUser(EntrepreneurProfile $profile): User
    {
        $user = User::factory()->create(['email' => $profile->email, 'user_type' => User::TYPE_ENTREPRENEUR, 'primary_role' => User::TYPE_ENTREPRENEUR]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);
        $profile->update(['user_id' => $user->getKey(), 'stage' => EntrepreneurStage::ONBOARDING]);
        $profile->inviteToken->markAccepted($user);

        return $user;
    }
}
