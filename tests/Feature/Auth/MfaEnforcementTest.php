<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\MfaFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Tests\TestCase;

final class MfaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_without_mfa_is_redirected_to_setup_before_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('mfa.setup'));
    }

    public function test_mfa_enrolled_user_without_verified_session_is_redirected_to_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('mfa.challenge'));
    }

    public function test_mfa_enrolled_user_with_verified_session_can_reach_dashboard(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_confirming_two_factor_marks_mfa_enabled_and_records_factor(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'mfa_enabled_at' => null,
            'mfa_method' => null,
        ]);

        $this->actingAs($user);
        event(new TwoFactorAuthenticationConfirmed($user));

        $user->refresh();

        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertSame(User::MFA_METHOD_TOTP, $user->mfa_method);
        $this->assertDatabaseHas('mfa_factors', [
            'user_id' => $user->id,
            'type' => MfaFactor::TYPE_TOTP,
        ]);
    }
}
