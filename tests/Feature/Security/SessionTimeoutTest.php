<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Security\StepUpEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_session_beyond_user_timeout_requires_reauthentication(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'session_timeout_minutes' => 1,
        ]);

        $this->actingAsMfa($user)
            ->withSession([
                StepUpEvaluator::SESSION_LAST_ACTIVITY_AT => now()->subMinutes(2)->getTimestamp(),
            ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'security.session_expired',
        ]);
    }

    public function test_active_session_inside_user_timeout_continues(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'session_timeout_minutes' => 5,
        ]);

        $this->actingAsMfa($user)
            ->withSession([
                StepUpEvaluator::SESSION_LAST_ACTIVITY_AT => now()->subMinute()->getTimestamp(),
            ])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_timeout_falls_back_to_user_type_config_and_allows_user_override(): void
    {
        $evaluator = app(StepUpEvaluator::class);

        $superAdmin = User::factory()->superAdmin()->make();
        $client = User::factory()->make([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $override = User::factory()->make([
            'session_timeout_minutes' => 7,
        ]);

        $this->assertSame(15, $evaluator->timeoutMinutes($superAdmin));
        $this->assertSame(60, $evaluator->timeoutMinutes($client));
        $this->assertSame(7, $evaluator->timeoutMinutes($override));
    }
}
