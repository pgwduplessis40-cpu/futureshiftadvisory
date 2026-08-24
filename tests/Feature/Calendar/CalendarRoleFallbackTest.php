<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CalendarRoleFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_calendar_entry_redirects_to_the_advisor_workspace(): void
    {
        $advisor = $this->user(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->get(route('calendar.index'))
            ->assertRedirect(route('advisor.calendar.index', absolute: false));
    }

    public function test_roles_without_calendar_records_receive_a_clear_empty_calendar(): void
    {
        foreach ([
            [User::TYPE_ENTREPRENEUR, 'Entrepreneur calendar'],
            [User::TYPE_ENTREPRENEUR_MENTOR, 'Mentor calendar'],
            [User::TYPE_BROKER, 'Broker calendar'],
            [User::TYPE_COACH, 'Coach calendar'],
        ] as [$userType, $title]) {
            $user = $this->user($userType);

            $this->actingAsMfa($user)
                ->get(route('calendar.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('calendar/Index')
                    ->where('title', $title)
                    ->where('events', [])
                    ->where('leavePeriods', [])
                    ->where('canManageLeavePeriods', false)
                );
        }
    }

    public function test_unknown_portal_role_receives_the_generic_empty_calendar(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => 'observer',
            'primary_role' => 'observer',
        ]);

        $this->actingAsMfa($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('title', 'Calendar')
                ->where('events', [])
                ->where('canManageLeavePeriods', false)
            );
    }

    private function user(string $userType): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => $userType,
            'primary_role' => $userType,
        ]);
        $user->assignRole($userType);

        return $user;
    }
}
