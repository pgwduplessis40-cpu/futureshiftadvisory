<?php

namespace Tests\Feature\Settings;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Notifications\EntrepreneurDeactivationRequestedNotification;
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
}
