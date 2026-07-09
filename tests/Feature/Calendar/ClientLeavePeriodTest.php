<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientLeavePeriod;
use App\Models\ClientTeamMember;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Services\Goals\GoalTracker;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientLeavePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $this->travelTo('2026-07-15 09:00:00');
    }

    public function test_client_can_record_leave_and_open_due_dates_move_after_leave(): void
    {
        [$clientUser, $client] = $this->clientUserAndClient();
        $goal = $this->goal($client);
        $milestone = Milestone::query()->create([
            'goal_id' => $goal->getKey(),
            'client_id' => $client->getKey(),
            'title' => 'Client evidence pack',
            'pv_of_impact' => 1000,
            'due_date' => '2026-08-05',
            'status' => Milestone::STATUS_PENDING,
        ]);
        $action = MilestoneAction::query()->create([
            'milestone_id' => $milestone->getKey(),
            'client_id' => $client->getKey(),
            'title' => 'Upload bank statements',
            'due_date' => '2026-08-06',
            'priority' => 'normal',
            'status' => MilestoneAction::STATUS_PENDING,
        ]);
        $strategicPlan = StrategicPlan::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Strategic Plan',
            'status' => StrategicPlan::STATUS_DEPLOYED,
            'summary' => 'Implementation plan.',
            'deployed_at' => now(),
        ]);
        $strategicMilestone = StrategicPlanMilestone::query()->create([
            'strategic_plan_id' => $strategicPlan->getKey(),
            'client_id' => $client->getKey(),
            'title' => 'Review first sprint',
            'owner' => StrategicPlanMilestone::OWNER_JOINT,
            'due_offset_days' => 30,
            'due_date' => '2026-08-07',
            'status' => StrategicPlanMilestone::STATUS_PENDING,
        ]);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('canManageLeavePeriods', true)
                ->where('leavePeriods', []));

        $this->actingAsMfa($clientUser)
            ->post(route('portal.calendar.leave-periods.store'), [
                'title' => 'Annual leave',
                'starts_on' => '2026-08-05',
                'ends_on' => '2026-08-07',
                'notes' => 'Away with limited access.',
            ])
            ->assertRedirect(route('portal.calendar.index', absolute: false))
            ->assertSessionHas('leave_rescheduled_count', 3);

        $this->assertDatabaseHas('client_leave_periods', [
            'client_id' => $client->getKey(),
            'title' => 'Annual leave',
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-07',
        ]);
        $this->assertSame('2026-08-08', $milestone->refresh()->due_date?->toDateString());
        $this->assertSame('2026-08-08', $action->refresh()->due_date?->toDateString());
        $this->assertSame('2026-08-08', $strategicMilestone->refresh()->due_date?->toDateString());

        $this->actingAsMfa($clientUser)
            ->get(route('portal.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('leavePeriods.0.title', 'Annual leave')
                ->where('events', fn (array $events): bool => collect($events)
                    ->contains(fn (array $event): bool => $event['kind'] === 'leave'
                        && $event['title'] === 'Annual leave')
                    && collect($events)->contains(fn (array $event): bool => $event['title'] === 'Goal milestone: Client evidence pack')
                    && collect($events)->contains(fn (array $event): bool => $event['title'] === 'Action: Upload bank statements')
                    && collect($events)->contains(fn (array $event): bool => $event['title'] === 'Strategic milestone: Review first sprint')));
    }

    public function test_leave_periods_block_new_meetings_milestones_and_actions(): void
    {
        [$clientUser, $client] = $this->clientUserAndClient();
        $advisor = $this->advisorFor($client);
        $goal = $this->goal($client);
        ClientLeavePeriod::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $clientUser->getKey(),
            'title' => 'Leave',
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-07',
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.calendar.meetings.store'), [
                'client_id' => $client->getKey(),
                'title' => 'Unavailable meeting',
                'scheduled_at' => '2026-08-06 10:00:00',
            ])
            ->assertSessionHasErrors('scheduled_at');

        try {
            app(GoalTracker::class)->createMilestone($goal, [
                'title' => 'Unavailable milestone',
                'due_date' => '2026-08-06',
            ], $advisor);
            $this->fail('Expected a validation error for a milestone during client leave.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_date', $exception->errors());
        }

        $milestone = app(GoalTracker::class)->createMilestone($goal, [
            'title' => 'Available milestone',
            'due_date' => '2026-08-08',
        ], $advisor);

        try {
            app(GoalTracker::class)->createAction($milestone, [
                'title' => 'Unavailable action',
                'due_date' => '2026-08-07',
            ], $advisor);
            $this->fail('Expected a validation error for an action during client leave.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_date', $exception->errors());
        }
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserAndClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Leave Calendar Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    private function advisorFor(Client $client): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $advisor;
    }

    private function goal(Client $client): Goal
    {
        return Goal::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Improve implementation cadence',
            'pv_target' => 10000,
            'status' => Goal::STATUS_ACTIVE,
        ]);
    }
}
