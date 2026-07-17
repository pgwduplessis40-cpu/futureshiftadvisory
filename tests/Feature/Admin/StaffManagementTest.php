<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\InviteToken;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_super_admin_can_view_staff_and_pending_staff_invites(): void
    {
        $admin = $this->superAdmin();
        $advisor = $this->advisor();
        InviteToken::query()->create([
            'email' => 'new.advisor@example.test',
            'token_hash' => InviteToken::hashToken('new-advisor-token'),
            'target_user_type' => User::TYPE_ADVISOR,
            'target_role' => User::TYPE_ADVISOR,
            'issued_by_user_id' => $admin->getKey(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/staff/Index')
                ->has('staff', 2)
                ->where('staff.0.email', $advisor->email)
                ->where('staff.0.advisor_client_capacity_limit', null)
                ->where('staff.0.client_capacity.active_count', 0)
                ->where('staff.0.client_capacity.limit', 30)
                ->has('pendingInvites', 1)
                ->where('pendingInvites.0.email', 'new.advisor@example.test')
                ->where('inviteUrl', route('admin.invitations.create', [
                    'return_to' => route('admin.staff.index', absolute: false),
                ], absolute: false)));
    }

    public function test_super_admin_can_update_staff_role_timeout_and_suspension(): void
    {
        $admin = $this->superAdmin();
        $advisor = $this->advisor();

        $this->actingAsMfa($admin)
            ->patch(route('admin.staff.update', $advisor), [
                'name' => 'Seed Junior Advisor',
                'user_type' => User::TYPE_JUNIOR_ADVISOR,
                'primary_role' => User::TYPE_JUNIOR_ADVISOR,
                'session_timeout_minutes' => 45,
                'advisor_client_capacity_limit' => 18,
                'suspended' => true,
                'suspended_reason' => 'Capacity pause',
            ])
            ->assertRedirect(route('admin.staff.index', absolute: false));

        $advisor->refresh();
        $this->assertSame('Seed Junior Advisor', $advisor->name);
        $this->assertSame(User::TYPE_JUNIOR_ADVISOR, $advisor->user_type);
        $this->assertSame(User::TYPE_JUNIOR_ADVISOR, $advisor->primary_role);
        $this->assertSame(45, $advisor->session_timeout_minutes);
        $this->assertSame(18, $advisor->advisor_client_capacity_limit);
        $this->assertNotNull($advisor->suspended_at);
        $this->assertSame('Capacity pause', $advisor->suspended_reason);
        $this->assertTrue($advisor->hasRole(User::TYPE_JUNIOR_ADVISOR));
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.staff.updated']);
    }

    public function test_advisors_cannot_access_staff_management(): void
    {
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function advisor(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
            'session_timeout_minutes' => 30,
        ]);
        $user->assignRole(User::TYPE_ADVISOR);

        return $user;
    }
}
