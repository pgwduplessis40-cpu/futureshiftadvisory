<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Panels\PanelOnboarding;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PanelAdminQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_queue_surfaces_applications_and_approves_to_agreement(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $coach = $this->panelUser(User::TYPE_COACH, 'queue-coach@example.test');
        $member = app(PanelOnboarding::class)->submitApplication($coach, PanelMember::TYPE_COACH, [
            'company' => 'Queue Coaching Limited',
            'specialties' => ['Leadership', 'Wellbeing'],
            'regions' => ['Auckland'],
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.panel-members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/panels/Index')
                ->has('members', 1)
                ->where('members.0.id', $member->id)
                ->where('members.0.status', PanelMember::STATUS_APPLICATION_PENDING)
                ->where('members.0.company', 'Queue Coaching Limited')
                ->where('members.0.approve_url', route('admin.panel-members.approve', $member, absolute: false)));

        $this->actingAsMfa($admin)
            ->patch(route('admin.panel-members.approve', $member))
            ->assertRedirect(route('admin.panel-members.index', absolute: false));

        $this->assertSame(PanelMember::STATUS_APPROVED_PENDING_AGREEMENT, $member->refresh()->status);
        $this->assertSame(PanelAgreement::STATUS_PENDING_SIGNATURE, $member->agreements()->firstOrFail()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.member_approved',
            'subject_id' => $member->id,
        ]);
    }

    public function test_admin_can_request_information_or_decline_panel_application_with_reason(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $broker = $this->panelUser(User::TYPE_BROKER, 'queue-broker@example.test');
        $member = app(PanelOnboarding::class)->submitApplication($broker, PanelMember::TYPE_BROKER, [
            'company' => 'Queue Brokers Limited',
            'fsp_number' => 'FSP100001',
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.panel-members.request-info', $member), [
                'reason' => 'Please attach current professional membership evidence.',
            ])
            ->assertRedirect(route('admin.panel-members.index', absolute: false));

        $this->assertSame(PanelMember::STATUS_INFORMATION_REQUESTED, $member->refresh()->status);
        $this->assertSame('information_requested', $member->application['review']['decision']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.application_information_requested',
            'subject_id' => $member->id,
        ]);

        $this->actingAsMfa($admin)
            ->patch(route('admin.panel-members.decline', $member), [
                'reason' => 'Application remains incomplete after review.',
            ])
            ->assertRedirect(route('admin.panel-members.index', absolute: false));

        $this->assertSame(PanelMember::STATUS_DECLINED, $member->refresh()->status);
        $this->assertSame('declined', $member->application['review']['decision']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'panel.application_declined',
            'subject_id' => $member->id,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function panelUser(string $type, string $email): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => $type,
            'primary_role' => $type,
        ]);
        $user->assignRole($type);

        return $user;
    }
}
