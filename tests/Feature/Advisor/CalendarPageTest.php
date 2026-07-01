<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Models\CalendarEventMapping;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Meeting;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        config([
            'integrations.calendar.google.live' => false,
            'integrations.calendar.microsoft.live' => false,
        ]);
    }

    public function test_advisor_can_create_list_edit_and_cancel_meetings_without_external_calendar(): void
    {
        [$advisor, $client] = $this->advisorAndClient('advisor-calendar-page@example.test');

        $this->actingAsMfa($advisor)
            ->get(route('advisor.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/calendar/Index')
                ->where('clients.0.id', $client->id)
                ->where('meetings', [])
            );

        $this->actingAsMfa($advisor)
            ->post(route('advisor.calendar.meetings.store'), [
                'client_id' => $client->id,
                'title' => 'Strategy check-in',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'location' => 'Board room',
                'attendees' => 'Owner, Advisor',
            ])
            ->assertRedirect(route('advisor.calendar.index', absolute: false));

        /** @var Meeting $meeting */
        $meeting = Meeting::query()->firstOrFail();

        $this->assertSame(Meeting::STATUS_SCHEDULED, $meeting->status);
        $this->assertSame(['Owner', 'Advisor'], $meeting->attendees);
        $this->assertSame(0, CalendarEventMapping::query()->count());

        $this->actingAsMfa($advisor)
            ->get(route('advisor.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('meetings.0.title', 'Strategy check-in')
                ->where('meetings.0.calendar_synced', false)
            );

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.calendar.meetings.update', $meeting), [
                'client_id' => $client->id,
                'title' => 'Updated strategy check-in',
                'scheduled_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'location' => 'Online',
                'link' => 'https://meet.example.test/strategy',
                'attendees' => 'Owner',
            ])
            ->assertRedirect(route('advisor.calendar.index', absolute: false));

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Updated strategy check-in',
            'location' => 'Online',
        ]);

        $this->actingAsMfa($advisor)
            ->delete(route('advisor.calendar.meetings.cancel', $meeting))
            ->assertRedirect(route('advisor.calendar.index', absolute: false));

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => Meeting::STATUS_CANCELLED,
            'cancelled_by_user_id' => $advisor->id,
        ]);
    }

    public function test_meeting_reminders_use_notification_center(): void
    {
        [$advisor, $client] = $this->advisorAndClient('advisor-calendar-reminder@example.test');
        $meeting = Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Reminder target meeting',
            'scheduled_at' => now()->addHours(2),
            'attendees' => [],
            'status' => Meeting::STATUS_SCHEDULED,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->artisan('meetings:send-reminders', ['--window-hours' => 24])
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $advisor->id,
            'type' => 'meeting.reminder',
        ]);
        $this->assertNotNull($meeting->refresh()->reminder_sent_at);
    }

    public function test_advisor_calendar_only_lists_accessible_client_meetings(): void
    {
        [$advisor, $client] = $this->advisorAndClient('advisor-calendar-owner@example.test');
        [$otherAdvisor, $otherClient] = $this->advisorAndClient('advisor-calendar-other@example.test');

        Meeting::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Visible meeting',
            'scheduled_at' => now()->addDays(2),
            'attendees' => [],
            'status' => Meeting::STATUS_SCHEDULED,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        Meeting::query()->create([
            'client_id' => $otherClient->getKey(),
            'title' => 'Hidden meeting',
            'scheduled_at' => now()->addDays(2),
            'attendees' => [],
            'status' => Meeting::STATUS_SCHEDULED,
            'created_by_user_id' => $otherAdvisor->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('meetings', fn ($meetings): bool => count($meetings) === 1
                    && $meetings[0]['title'] === 'Visible meeting')
            );
    }

    public function test_advisor_calendar_lists_public_holidays_for_accessible_client_regions(): void
    {
        $this->travelTo('2026-07-01 09:00:00');

        [$advisor] = $this->advisorAndClient('advisor-calendar-holidays@example.test', 'Waikato');

        $this->actingAsMfa($advisor)
            ->get(route('advisor.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('publicHolidays', fn (array $holidays): bool => collect($holidays)
                    ->contains(fn (array $holiday): bool => $holiday['title'] === 'Matariki')
                    && collect($holidays)->contains(fn (array $holiday): bool => $holiday['title'] === 'Labour Day')
                    && ! collect($holidays)->contains(fn (array $holiday): bool => $holiday['title'] === 'Canterbury South Anniversary Day'))
            );
    }

    public function test_meetings_cannot_be_scheduled_on_public_holidays_for_the_client_region(): void
    {
        $this->travelTo('2026-07-01 09:00:00');

        [$advisor, $waikatoClient] = $this->advisorAndClient('advisor-calendar-holiday-block@example.test', 'Waikato');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.calendar.meetings.store'), [
                'client_id' => $waikatoClient->id,
                'title' => 'Matariki strategy meeting',
                'scheduled_at' => '2026-07-10 10:00:00',
            ])
            ->assertSessionHasErrors('scheduled_at');

        $this->assertDatabaseMissing('meetings', [
            'title' => 'Matariki strategy meeting',
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.calendar.meetings.store'), [
                'client_id' => $waikatoClient->id,
                'title' => 'North Island meeting on South Canterbury anniversary',
                'scheduled_at' => '2026-09-28 10:00:00',
            ])
            ->assertRedirect(route('advisor.calendar.index', absolute: false));

        $this->assertDatabaseHas('meetings', [
            'title' => 'North Island meeting on South Canterbury anniversary',
        ]);

        [$southAdvisor, $southCanterburyClient] = $this->advisorAndClient(
            'advisor-calendar-south-canterbury@example.test',
            'South Canterbury',
        );

        $this->actingAsMfa($southAdvisor)
            ->post(route('advisor.calendar.meetings.store'), [
                'client_id' => $southCanterburyClient->id,
                'title' => 'South Canterbury anniversary meeting',
                'scheduled_at' => '2026-09-28 10:00:00',
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(string $email, ?string $region = null): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => 'Calendar Page Limited',
            'address' => $region ? ['region' => $region] : null,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }
}
