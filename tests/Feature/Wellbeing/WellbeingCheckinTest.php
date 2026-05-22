<?php

declare(strict_types=1);

namespace Tests\Feature\Wellbeing;

use App\Console\Commands\SendWellbeingCheckinPrompts;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Notifications\WellbeingCheckinPromptNotification;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WellbeingCheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_client_can_skip_or_submit_the_optional_monthly_pulse(): void
    {
        [$user, $client] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('client.id', $client->id)
                ->where('wellbeing.prompt_due', true));

        $this->actingAsMfa($user)
            ->get(route('portal.wellbeing.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/wellbeing/Pulse')
                ->where('currentCheckin', null));

        $this->actingAsMfa($user)
            ->post(route('portal.wellbeing.store'), [
                'business_confidence' => 4,
                'personal_coping' => 3,
                'notes' => 'A little busy, but manageable.',
            ])
            ->assertRedirect(route('portal.wellbeing.show', absolute: false));

        $this->assertDatabaseHas('wellbeing_checkins', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'business_confidence' => 4,
            'personal_coping' => 3,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'wellbeing.submitted',
            'client_id' => $client->id,
        ]);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('wellbeing.prompt_due', false));
    }

    public function test_client_can_delete_own_checkin_only_within_seven_days(): void
    {
        [$user, $client] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->post(route('portal.wellbeing.store'), [
                'business_confidence' => 3,
                'personal_coping' => 3,
                'notes' => null,
            ]);

        $checkin = WellbeingCheckin::query()->firstOrFail();

        $this->actingAsMfa($user)
            ->delete(route('portal.wellbeing.destroy', $checkin))
            ->assertRedirect(route('portal.wellbeing.show', absolute: false));

        $this->assertDatabaseMissing('wellbeing_checkins', [
            'id' => $checkin->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'wellbeing.deleted',
            'client_id' => $client->id,
        ]);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());
        $old = WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'business_confidence' => 3,
            'personal_coping' => 3,
            'submitted_at' => now()->subDays(8),
        ]);

        $this->actingAsMfa($user)
            ->delete(route('portal.wellbeing.destroy', $old))
            ->assertSessionHasErrors('checkin');

        app(RequestContext::class)->apply('system', []);
        $this->assertDatabaseHas('wellbeing_checkins', [
            'id' => $old->id,
        ]);
    }

    public function test_two_consecutive_low_personal_coping_scores_create_internal_signal_without_referral(): void
    {
        [$user, $client] = $this->clientUserWithClient();

        app(RequestContext::class)->apply('system', []);
        WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'business_confidence' => 3,
            'personal_coping' => 2,
            'submitted_at' => now()->subMonthNoOverflow(),
        ]);

        $this->assertDatabaseCount('coaching_signals', 0);

        $this->actingAsMfa($user)
            ->post(route('portal.wellbeing.store'), [
                'business_confidence' => 3,
                'personal_coping' => 2,
                'notes' => 'Still under pressure.',
            ]);

        app(RequestContext::class)->apply('system', []);
        $signal = CoachingSignal::query()->firstOrFail();

        $this->assertSame($client->id, $signal->client_id);
        $this->assertSame(CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK, $signal->signal_type);
        $this->assertFalse($signal->evidence['auto_referral']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'coaching_signal.detected',
            'client_id' => $client->id,
        ]);
    }

    public function test_low_personal_coping_streak_records_one_raw_signal_only(): void
    {
        [$user, $client] = $this->clientUserWithClient('streak.client@example.com');

        app(RequestContext::class)->apply('system', []);
        WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'business_confidence' => 3,
            'personal_coping' => 2,
            'submitted_at' => now()->subMonthNoOverflow(),
        ]);

        $this->actingAsMfa($user)
            ->post(route('portal.wellbeing.store'), [
                'business_confidence' => 3,
                'personal_coping' => 2,
                'notes' => 'Second low month.',
            ]);

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDay());

        $this->actingAsMfa($user)
            ->post(route('portal.wellbeing.store'), [
                'business_confidence' => 3,
                'personal_coping' => 2,
                'notes' => 'Still low, same streak.',
            ]);

        app(RequestContext::class)->apply('system', []);
        $signal = CoachingSignal::query()->sole();

        $this->assertSame(CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK, $signal->signal_type);
        $this->assertSame('raw_internal_observation_only', $signal->evidence['phase_2_boundary']);
        $this->assertFalse($signal->evidence['auto_referral']);
        $this->assertDatabaseCount('learning_updates', 0);
    }

    public function test_wellbeing_trend_is_visible_to_lead_advisor_and_super_admin_only(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient();
        $advisor = $this->advisorFor($client, User::TYPE_ADVISOR, 'lead_advisor');
        $junior = $this->advisorFor($client, User::TYPE_JUNIOR_ADVISOR, 'support_advisor');
        $superAdmin = $this->superAdmin();

        app(RequestContext::class)->apply('system', [], (string) $clientUser->getKey());
        WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'business_confidence' => 4,
            'personal_coping' => 3,
            'notes' => 'A useful advisor-only note.',
            'submitted_at' => now(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('client.wellbeing_trend', 1)
                ->where('client.wellbeing_trend.0.notes', 'A useful advisor-only note.'));

        $this->actingAsMfa($superAdmin)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('client.wellbeing_trend', 1));

        $this->actingAsMfa($junior)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('client.wellbeing_trend', null));
    }

    public function test_advisor_dashboard_surfaces_scoped_wellbeing_analytics(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient('wellbeing-dashboard-client@example.com');
        $advisor = $this->advisorFor($client, User::TYPE_ADVISOR, 'lead_advisor');
        [$otherUser, $otherClient] = $this->clientUserWithClient('wellbeing-dashboard-other@example.com');
        $this->advisorFor($otherClient, User::TYPE_ADVISOR, 'lead_advisor');

        app(RequestContext::class)->apply('system', []);
        WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->id,
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'business_confidence' => 4,
            'personal_coping' => 3,
            'submitted_at' => now()->subMonthNoOverflow(),
        ]);
        WellbeingCheckin::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'business_confidence' => 2,
            'personal_coping' => 2,
            'submitted_at' => now(),
        ]);
        WellbeingCheckin::query()->create([
            'client_id' => $otherClient->id,
            'user_id' => $otherUser->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'business_confidence' => 1,
            'personal_coping' => 1,
            'submitted_at' => now(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('wellbeingAnalytics.summary.checkins', 2)
                ->where('wellbeingAnalytics.summary.clients', 1)
                ->where('wellbeingAnalytics.summary.average_personal_coping', 2.5)
                ->where('wellbeingAnalytics.summary.low_personal_coping_checkins', 1)
                ->where('wellbeingAnalytics.summary.current_period_completion_rate', 1)
                ->has('wellbeingAnalytics.monthly', 2));
    }

    public function test_monthly_command_prompts_due_client_users_only(): void
    {
        Notification::fake();
        [$dueUser] = $this->clientUserWithClient('due.client@example.com');
        [$doneUser, $doneClient] = $this->clientUserWithClient('done.client@example.com');

        app(RequestContext::class)->apply('system', [], (string) $doneUser->getKey());
        WellbeingCheckin::query()->create([
            'client_id' => $doneClient->id,
            'user_id' => $doneUser->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'business_confidence' => 4,
            'personal_coping' => 4,
            'submitted_at' => now(),
        ]);

        $this->artisan(SendWellbeingCheckinPrompts::class)
            ->assertSuccessful();

        Notification::assertSentTo($dueUser, WellbeingCheckinPromptNotification::class);
        Notification::assertNotSentTo($doneUser, WellbeingCheckinPromptNotification::class);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(string $email = 'client.owner@example.com'): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000200',
            'legal_name' => 'Wellbeing Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    private function advisorFor(Client $client, string $userType, string $teamRole): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => $userType,
            'primary_role' => $userType,
        ]);
        $user->assignRole($userType);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => $teamRole,
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
