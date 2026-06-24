<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\MfaFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_mfa_setup_page_shows_guided_setup_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('mfa.setup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/mfa-setup')
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false)
                ->where('hasPendingTwoFactorSetup', false)
                ->where('requiresConfirmation', true)
            );
    }

    public function test_mfa_setup_page_continues_pending_unconfirmed_secret(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('pending-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => null,
            'mfa_enabled_at' => null,
            'mfa_method' => null,
        ]);

        $this->actingAs($user)
            ->get(route('mfa.setup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/mfa-setup')
                ->where('twoFactorEnabled', false)
                ->where('hasPendingTwoFactorSetup', true)
            );
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

    #[DataProvider('userTypeProvider')]
    public function test_two_factor_qr_code_can_be_generated_for_every_profile_type(string $userType): void
    {
        $user = User::factory()->create([
            'user_type' => $userType,
            'primary_role' => $userType,
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->post(route('two-factor.enable'))
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->getJson(route('two-factor.qr-code'));

        $response
            ->assertOk()
            ->assertJsonStructure(['svg', 'url']);

        $this->assertNotNull($user->refresh()->two_factor_secret);
        $this->assertIsString($response->json('svg'));
        $this->assertStringContainsString('<svg', $response->json('svg'));
        $this->assertIsString($response->json('url'));
        $this->assertStringStartsWith('otpauth://totp/', $response->json('url'));
    }

    public function test_two_factor_qr_modal_keeps_svg_scannable_on_white_background(): void
    {
        $component = file_get_contents(resource_path('js/components/two-factor-setup-modal.tsx'));

        $this->assertIsString($component);
        $this->assertStringContainsString('rounded-lg bg-white p-2', $component);
        $this->assertStringNotContainsString('dark:invert', $component);
        $this->assertStringNotContainsString('invert', $component);
    }

    public function test_mfa_challenge_locks_after_three_failed_attempts(): void
    {
        config([
            'security.mfa_failed_attempt_limit' => 3,
            'security.mfa_lockout_minutes' => 15,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user);

        $this->post(route('mfa.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->post(route('mfa.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->post(route('mfa.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->post(route('mfa.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors([
                'code' => 'Too many incorrect authentication attempts. Try again in 15 minutes.',
            ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function userTypeProvider(): array
    {
        return collect(User::userTypes())
            ->mapWithKeys(fn (string $userType): array => [$userType => [$userType]])
            ->all();
    }
}
