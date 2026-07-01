<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\PublicHolidayCalendar;
use Tests\TestCase;

final class PublicHolidayCalendarTest extends TestCase
{
    public function test_national_public_holidays_apply_without_a_region(): void
    {
        $calendar = new PublicHolidayCalendar;

        $events = collect($calendar->eventsBetween('2026-07-01', '2026-07-31'));

        $this->assertTrue($events->contains(fn (array $event): bool => $event['title'] === 'Matariki'));
        $this->assertTrue($calendar->isPublicHoliday('2026-07-10'));
        $this->assertTrue($calendar->isPublicHoliday('2026-04-25'));
        $this->assertTrue($calendar->isPublicHoliday('2026-04-27'));
    }

    public function test_regional_anniversary_days_only_apply_to_matching_client_regions(): void
    {
        $calendar = new PublicHolidayCalendar;

        $this->assertFalse($calendar->isPublicHoliday('2026-09-28', ['Waikato']));
        $this->assertTrue($calendar->isPublicHoliday('2026-09-28', ['South Canterbury']));

        $waikatoEvents = collect($calendar->eventsBetween('2026-09-01', '2026-10-31', ['Waikato']));
        $southCanterburyEvents = collect($calendar->eventsBetween('2026-09-01', '2026-10-31', ['South Canterbury']));

        $this->assertFalse($waikatoEvents->contains(fn (array $event): bool => $event['title'] === 'Canterbury South Anniversary Day'));
        $this->assertTrue($southCanterburyEvents->contains(fn (array $event): bool => $event['title'] === 'Canterbury South Anniversary Day'));
        $this->assertTrue($waikatoEvents->contains(fn (array $event): bool => $event['title'] === 'Labour Day'));
    }

    public function test_generated_dates_shift_forward_only_when_the_client_region_is_closed(): void
    {
        $calendar = new PublicHolidayCalendar;

        $this->assertSame('2026-09-28', $calendar->nextAvailableDate('2026-09-28', ['Waikato'])->toDateString());
        $this->assertSame('2026-09-29', $calendar->nextAvailableDate('2026-09-28', ['South Canterbury'])->toDateString());
    }
}
