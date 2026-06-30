<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Security\MfaChallenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/two-factor-challenge'),
            );
    }

    public function test_two_factor_challenge_marks_mfa_session_after_login(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $this->app->instance(TwoFactorAuthenticationProvider::class, new class implements TwoFactorAuthenticationProvider
        {
            public function generateSecretKey(): string
            {
                return 'secret';
            }

            public function qrCodeUrl($companyName, $companyEmail, $secret): string
            {
                return '';
            }

            public function verify($secret, $code): bool
            {
                return $secret === 'secret' && $code === '123456';
            }
        });

        $user = User::factory()->withTwoFactor()->create();

        $this
            ->withSession([
                'login.id' => $user->getKey(),
                'login.remember' => false,
                'url.intended' => route('admin.service-rates.index', absolute: false),
            ])
            ->post(route('two-factor.login'), [
                'code' => '123456',
            ])
            ->assertRedirect(config('fortify.home'))
            ->assertSessionHas(MfaChallenger::SESSION_USER_ID, (string) $user->getKey());

        $this->assertAuthenticatedAs($user);
    }
}
