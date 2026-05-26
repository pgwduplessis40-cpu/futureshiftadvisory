<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AddEntrepreneurTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_can_create_entrepreneur_profile_and_issue_invite(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store'), [
                'name' => 'Aroha Founder',
                'email' => 'Aroha.Founder@example.com',
                'concept_summary' => 'Circular retail analytics for regional stores.',
                'stage' => EntrepreneurStage::READINESS->value,
            ])
            ->assertRedirect();

        $profile = EntrepreneurProfile::query()->firstOrFail();
        $invite = InviteToken::query()->firstOrFail();

        $this->assertSame('aroha.founder@example.com', $profile->email);
        $this->assertSame('Aroha Founder', $profile->name);
        $this->assertSame(EntrepreneurStage::INVITED, $profile->stage);
        $this->assertSame($advisor->id, $profile->assigned_advisor_id);
        $this->assertSame($invite->id, $profile->invite_token_id);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $invite->target_user_type);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $invite->target_role);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.created']);
    }

    public function test_accepting_invite_links_profile_and_moves_to_onboarding(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();

        $issued = app(InviteIssuer::class)->issue(
            email: 'founder@example.com',
            targetUserType: User::TYPE_ENTREPRENEUR,
            targetRole: User::TYPE_ENTREPRENEUR,
            issuedBy: $advisor,
        );
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->id,
            'invite_token_id' => $issued->invite->id,
            'name' => 'Founder Person',
            'email' => 'founder@example.com',
            'stage' => EntrepreneurStage::INVITED,
            'concept_summary' => 'Specialist onboarding concept.',
        ]);

        $this->post(route('invite.store', $issued->plainToken), [
            'name' => 'Founder Person',
            'password' => 'A-secure-password-123',
            'password_confirmation' => 'A-secure-password-123',
        ])->assertRedirect(route('mfa.setup', absolute: false));

        $user = User::query()->where('email', 'founder@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::TYPE_ENTREPRENEUR, $user->user_type);
        $this->assertSame($user->id, $profile->refresh()->user_id);
        $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.onboarding_started']);
    }

    public function test_capacity_warning_is_exposed_at_twenty_four_active_entrepreneurs(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $this->createProfiles($advisor, 24);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.entrepreneurs.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/entrepreneurs/Create')
                ->where('capacity.active_count', 24)
                ->where('capacity.warning_threshold', 24)
                ->where('capacity.warning', true)
                ->where('capacity.blocked', false)
            );
    }

    public function test_capacity_hard_blocks_at_thirty_active_entrepreneurs(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $this->createProfiles($advisor, 30);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.store'), [
                'name' => 'Blocked Founder',
                'email' => 'blocked@example.com',
                'concept_summary' => 'Should not be invited.',
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertDatabaseCount('entrepreneur_profiles', 30);
        $this->assertDatabaseCount('invite_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_entrepreneur_dashboard_redirects_to_actionable_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Portal Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Portal placeholder concept.',
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.entrepreneur.dashboard', absolute: false));

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('profile.stage', EntrepreneurStage::ONBOARDING->value)
                ->where('profile.name', 'Portal Founder')
                ->where('profile.message_summary.threads_count', 0)
                ->where('messagesUrl', route('portal.messages.index', absolute: false))
                ->where('documentUploadUrl', route('portal.documents.store', absolute: false))
            );
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function createProfiles(User $advisor, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->id,
                'name' => "Founder {$i}",
                'email' => "founder{$i}@example.com",
                'stage' => EntrepreneurStage::INVITED,
            ]);
        }
    }
}
