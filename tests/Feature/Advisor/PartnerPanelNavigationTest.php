<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Models\InviteToken;
use App\Models\PanelMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PartnerPanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_broker_list_and_card(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $broker = $this->panelUser(User::TYPE_BROKER, 'broker@example.test');

        $member = PanelMember::query()->create([
            'user_id' => $broker->id,
            'panel_type' => PanelMember::TYPE_BROKER,
            'status' => 'approved',
            'application' => [
                'company' => 'North Shore Broker Partners',
                'broker_name' => 'Aroha Broker',
                'industry' => 'life insurance',
                'regions' => ['Auckland'],
                'specialties' => ['Life insurance'],
            ],
            'fsp_number' => 'FSP100001',
            'fsp_status' => PanelMember::FSP_STATUS_CURRENT,
            'approved_at' => now(),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.partners.brokers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/partners/Index')
                ->where('title', 'Brokers')
                ->where('industryColumnLabel', 'Industry')
                ->has('partners', 1)
                ->where('partners.0.business_name', 'North Shore Broker Partners')
                ->where('partners.0.contact_name', 'Aroha Broker')
                ->where('partners.0.industry_label', 'Life insurance')
                ->where('partners.0.show_url', route('advisor.partners.show', $member, absolute: false)));

        $this->actingAsMfa($admin)
            ->get(route('advisor.partners.show', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/partners/Show')
                ->where('partner.business_name', 'North Shore Broker Partners')
                ->where('partner.contact_name', 'Aroha Broker')
                ->where('partner.fsp_number', 'FSP100001')
                ->where('partner.back_url', route('advisor.partners.brokers.index', absolute: false)));
    }

    public function test_super_admin_can_view_coach_list(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $coach = $this->panelUser(User::TYPE_COACH, 'coach@example.test');

        PanelMember::query()->create([
            'user_id' => $coach->id,
            'panel_type' => PanelMember::TYPE_COACH,
            'status' => PanelMember::STATUS_ACTIVE,
            'application' => [
                'company' => 'Leadership Coach Studio',
                'coach_name' => 'Mika Coach',
                'regions' => ['Waikato'],
            ],
            'coach_specialisations' => ['Leadership rhythm'],
            'approved_at' => now(),
        ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.partners.coaches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/partners/Index')
                ->where('title', 'Coaches')
                ->where('industryColumnLabel', 'Focus')
                ->has('partners', 1)
                ->where('partners.0.business_name', 'Leadership Coach Studio')
                ->where('partners.0.contact_name', 'Mika Coach')
                ->where('partners.0.industry_label', 'Leadership rhythm'));
    }

    public function test_super_admin_can_invite_broker_and_coach_partners(): void
    {
        Mail::fake();

        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('advisor.partners.brokers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/partners/Create')
                ->where('panelType', PanelMember::TYPE_BROKER)
                ->where('panelLabel', 'Broker'));

        $this->actingAsMfa($admin)
            ->post(route('advisor.partners.brokers.store'), [
                'business_name' => 'Bay Broker Partners',
                'contact_name' => 'Hemi Broker',
                'email' => 'invite-broker@example.test',
                'industry' => 'business_insurance',
                'notes' => 'Useful SME insurance contact.',
            ])
            ->assertRedirect(route('advisor.partners.brokers.index', absolute: false));

        $brokerInvite = InviteToken::query()
            ->where('email', 'invite-broker@example.test')
            ->firstOrFail();
        $brokerMember = PanelMember::query()
            ->where('invite_token_id', $brokerInvite->id)
            ->firstOrFail();

        $this->assertSame(User::TYPE_BROKER, $brokerInvite->target_user_type);
        $this->assertSame(PanelMember::STATUS_INVITED, $brokerMember->status);
        $this->assertSame('Bay Broker Partners', data_get($brokerMember->application, 'company'));
        $this->assertSame('Hemi Broker', data_get($brokerMember->application, 'broker_name'));
        $this->assertSame('Business insurance', data_get($brokerMember->application, 'industry'));

        $this->actingAsMfa($admin)
            ->post(route('advisor.partners.coaches.store'), [
                'business_name' => 'Founder Coach Studio',
                'contact_name' => 'Mere Coach',
                'email' => 'invite-coach@example.test',
                'focus' => 'Founder resilience',
                'notes' => null,
            ])
            ->assertRedirect(route('advisor.partners.coaches.index', absolute: false));

        $coachInvite = InviteToken::query()
            ->where('email', 'invite-coach@example.test')
            ->firstOrFail();
        $coachMember = PanelMember::query()
            ->where('invite_token_id', $coachInvite->id)
            ->firstOrFail();

        $this->assertSame(User::TYPE_COACH, $coachInvite->target_user_type);
        $this->assertSame(PanelMember::STATUS_INVITED, $coachMember->status);
        $this->assertSame('Founder Coach Studio', data_get($coachMember->application, 'company'));
        $this->assertSame('Mere Coach', data_get($coachMember->application, 'coach_name'));
        $this->assertSame(['Founder resilience'], data_get($coachMember->application, 'specialties'));
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
