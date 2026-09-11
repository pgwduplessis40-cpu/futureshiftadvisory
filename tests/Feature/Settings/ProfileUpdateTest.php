<?php

namespace Tests\Feature\Settings;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PaymentRefund;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Notifications\EntrepreneurDeactivationRequestedNotification;
use App\Services\Entrepreneurs\IdeaValidationCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsMfa($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_install_app_settings_page_is_displayed()
    {
        $user = User::factory()->create();

        $this
            ->actingAsMfa($user)
            ->get(route('install-app.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/install-app')
            );
    }

    public function test_entrepreneur_profile_page_exposes_deactivation_request_state()
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);

        $this
            ->actingAsMfa($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('deactivationRequestedAt', null)
            );
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsMfa($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_update_cannot_mass_assign_identity_or_authentication_fields(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
            'session_timeout_minutes' => 30,
        ]);

        $this
            ->actingAsMfa($user)
            ->patch(route('profile.update'), [
                'name' => 'Safe Profile Name',
                'email' => $user->email,
                'user_type' => User::TYPE_SUPER_ADMIN,
                'primary_role' => User::TYPE_SUPER_ADMIN,
                'mfa_enabled_at' => null,
                'mfa_method' => 'none',
                'session_timeout_minutes' => 1,
                'advisor_client_capacity_limit' => 999,
                'suspended_at' => now()->toIso8601String(),
                'suspended_reason' => 'forged request field',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Safe Profile Name', $user->name);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->user_type);
        $this->assertSame(User::TYPE_CLIENT_PRIMARY, $user->primary_role);
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertSame(User::MFA_METHOD_TOTP, $user->mfa_method);
        $this->assertSame(30, $user->session_timeout_minutes);
        $this->assertNull($user->advisor_client_capacity_limit);
        $this->assertNull($user->suspended_at);
        $this->assertNull($user->suspended_reason);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsMfa($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_cannot_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsMfa($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsMfa($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_entrepreneur_cannot_delete_their_account()
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);

        $this
            ->actingAsMfa($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_entrepreneur_can_request_deactivation_with_confirmation()
    {
        Notification::fake();

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $user = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'user_id' => $user->getKey(),
            'name' => 'Request Founder',
            'email' => $user->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Request deactivation test.',
        ]);

        $this
            ->actingAsMfa($user)
            ->from(route('profile.edit'))
            ->post(route('profile.deactivation-request'), [
                'confirm_deactivation' => 'yes',
                'reason' => 'I am pausing the venture.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->deactivation_requested_at);
        $this->assertSame('I am pausing the venture.', $user->deactivation_requested_reason);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'user.deactivation_requested',
            'subject_type' => User::class,
            'subject_id' => (string) $user->getKey(),
        ]);
        Notification::assertSentTo($advisor, EntrepreneurDeactivationRequestedNotification::class);

        $this
            ->actingAsMfa($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deactivationRequestedAt', $user->deactivation_requested_at?->toIso8601String())
            );
    }

    public function test_broker_can_request_deactivation_with_confirmation()
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_BROKER,
            'primary_role' => User::TYPE_BROKER,
        ]);

        $this
            ->actingAsMfa($user)
            ->from(route('profile.edit'))
            ->post(route('profile.deactivation-request'), [
                'confirm_deactivation' => 'yes',
                'reason' => 'Panel access no longer needed.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->deactivation_requested_at);
        $this->assertSame('Panel access no longer needed.', $user->deactivation_requested_reason);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'user.deactivation_requested',
            'subject_type' => User::class,
            'subject_id' => (string) $user->getKey(),
        ]);
    }

    public function test_entrepreneur_deactivation_request_requires_confirmation()
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);

        $this
            ->actingAsMfa($user)
            ->from(route('profile.edit'))
            ->post(route('profile.deactivation-request'), [])
            ->assertSessionHasErrors('confirm_deactivation')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()?->deactivation_requested_at);
    }

    public function test_paid_unsubmitted_idea_validation_can_be_cancelled_refunded_and_deactivated(): void
    {
        Notification::fake();
        [$user, $client, $activation] = $this->paidIdeaValidationClient();

        $this
            ->actingAsMfa($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ideaValidationCancellation.eligible', true)
                ->where('ideaValidationCancellation.refund_amount', '1897.50')
                ->where('ideaValidationCancellation.currency', 'NZD')
            );

        $this
            ->actingAsMfa($user)
            ->post(route('profile.idea-validation.cancel'), [
                'confirm_cancellation' => 'yes',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(IdeaValidationCancellation::SUSPENSION_REASON, $user->refresh()->suspended_reason);
        $this->assertNotNull($user->suspended_at);

        $this
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(ClientStatus::SUSPENDED, $client->refresh()->status);
        $this->assertSame(ServiceActivation::STATUS_CANCELLED, $activation->refresh()->status);
        $this->assertNotNull($activation->cancelled_at);
        $this->assertDatabaseHas('payment_refunds', [
            'service_activation_id' => $activation->getKey(),
            'status' => PaymentRefund::STATUS_ACCEPTED,
            'payment_reference' => 'pi_profile_idea_cancellation',
            'amount' => '1897.50',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.idea_validation_cancelled',
            'subject_id' => (string) $activation->getKey(),
        ]);
    }

    public function test_submitted_idea_validation_cannot_be_cancelled_or_refunded(): void
    {
        [$user, , $activation] = $this->paidIdeaValidationClient();
        $profile = EntrepreneurProfile::query()->where('user_id', $user->getKey())->firstOrFail();
        IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'revision_number' => 1,
            'problem' => 'Founders cannot understand which problem they should solve first.',
            'target_customer' => 'Owner-managed service businesses in New Zealand.',
            'solution' => 'A guided idea validation service.',
            'value_proposition' => 'Founder clarity before business-plan work begins.',
            'demand_signal' => 'Five founders asked for this support during discovery calls.',
            'revenue_model' => 'A fixed validation fee paid before advisor review.',
            'ai_evaluation' => [],
            'viability_alerts' => [],
            'evaluated_by_user_id' => $user->getKey(),
            'evaluated_at' => now(),
        ]);

        $this
            ->actingAsMfa($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ideaValidationCancellation.eligible', false)
                ->where('ideaValidationCancellation.reason', 'submitted')
            );

        $this
            ->actingAsMfa($user)
            ->from(route('profile.edit'))
            ->post(route('profile.idea-validation.cancel'), [
                'confirm_cancellation' => 'yes',
            ])
            ->assertSessionHasErrors('cancellation')
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->refresh()->status);
        $this->assertDatabaseMissing('payment_refunds', [
            'service_activation_id' => $activation->getKey(),
        ]);
    }

    /** @return array{0:User,1:Client,2:ServiceActivation} */
    private function paidIdeaValidationClient(): array
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $user = User::factory()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE->value,
            'legal_name' => 'Idea Cancellation Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::ENTREPRENEUR_MODULE->value],
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::ENTREPRENEUR_MODULE->value],
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'name' => 'Idea Cancellation Founder',
            'email' => $user->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'A paid Idea Validation client who has not submitted.',
        ]);
        $package = ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation cancellation test',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1650,
            'currency' => 'NZD',
            'scope_description' => 'Idea Validation only.',
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);
        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'advisor_id' => $advisor->getKey(),
            'approved_by_user_id' => $advisor->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Idea Validation',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'selected_package_snapshot' => $package->snapshot(),
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_completed_at' => now()->subHour(),
            'payment_completed_by_user_id' => $user->getKey(),
            'payment_reference' => 'pi_profile_idea_cancellation',
            'accepted_at' => now()->subHour(),
            'accepted_by_user_id' => $user->getKey(),
            'related_entrepreneur_profile_id' => $profile->getKey(),
        ]);

        return [$user, $client, $activation];
    }
}
