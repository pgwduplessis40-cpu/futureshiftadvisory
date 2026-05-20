<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StepUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_route_from_changed_device_requires_step_up_mfa(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->superAdmin();

        $this->actingAsMfa($user)
            ->withHeaders(['User-Agent' => 'Known Device'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->withHeaders(['User-Agent' => 'New Device'])
            ->get(route('admin.invitations.index'))
            ->assertRedirect(route('mfa.challenge', ['reason' => 'step_up']));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'security.step_up_required',
        ]);
    }

    public function test_failed_step_up_challenge_is_audit_logged(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->superAdmin();

        $this->actingAsMfa($user)
            ->withHeaders(['User-Agent' => 'Known Device'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->withHeaders(['User-Agent' => 'New Device'])
            ->get(route('admin.invitations.index'))
            ->assertRedirect(route('mfa.challenge', ['reason' => 'step_up']));

        $this->post(route('mfa.challenge.store'), [
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseHas('audit_events', [
            'action' => 'security.step_up_failed',
        ]);
    }

    public function test_same_device_super_admin_route_does_not_require_step_up(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->superAdmin();

        $this->actingAsMfa($user)
            ->withHeaders(['User-Agent' => 'Known Device'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->withHeaders(['User-Agent' => 'Known Device'])
            ->get(route('admin.invitations.index'))
            ->assertOk();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
