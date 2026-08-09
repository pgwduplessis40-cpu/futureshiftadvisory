<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalHealthCheckRun;
use App\Services\OperationalHealth\OperationalHealthAlerter;
use App\Services\OperationalHealth\OperationalHealthCheckRunner;
use App\Services\OperationalHealth\OperationalHealthSchedule;
use Carbon\CarbonInterface;
use Database\Seeders\OperationalHealthFixtureSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunDueOperationalHealthChecks extends Command
{
    protected $signature = 'fsa:operational-health-check:due
                            {--ensure-fixtures : Provision idempotent monitor fixtures before running checks.}
                            {--grace-minutes=10 : Minutes after a scheduled slot before catch-up is allowed.}
                            {--limit= : Maximum missed scheduled slots to run.}
                            {--dry-run : List missed scheduled slots without running checks.}';

    protected $description = 'Run missed scheduled operational health checks once per due schedule slot.';

    public function __construct(private readonly OperationalHealthSchedule $schedule)
    {
        parent::__construct();
    }

    public function handle(OperationalHealthCheckRunner $runner, OperationalHealthAlerter $alerter): int
    {
        if (! (bool) config('operational_health.enabled', false)) {
            $this->info('Operational health checks are disabled.');

            return self::SUCCESS;
        }

        $timezone = $this->schedule->timezone();
        $now = Carbon::now($timezone);
        $graceMinutes = $this->integerOption('grace-minutes', minimum: 0, fallback: 10);
        $limit = $this->nullableIntegerOption('limit', minimum: 1);
        $missedSlots = $this->missedSlots($now, $graceMinutes);

        if ($limit !== null) {
            $missedSlots = array_slice($missedSlots, 0, $limit);
        }

        if ($missedSlots === []) {
            $this->info(sprintf(
                'No missed operational health checks are due as at %s.',
                $now->format('d M Y, g:i A T'),
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Scheduled for', 'Timezone'],
            array_map(
                fn (array $slot): array => [$slot['scheduled_at']->format('d M Y, g:i A'), $timezone],
                $missedSlots,
            ),
        );

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if ((bool) config('operational_health.ensure_fixtures', true) || (bool) $this->option('ensure-fixtures')) {
            $this->callSilent('db:seed', [
                '--class' => OperationalHealthFixtureSeeder::class,
                '--force' => true,
            ]);
        }

        foreach ($missedSlots as $slot) {
            /** @var Carbon $scheduledAt */
            $scheduledAt = $slot['scheduled_at'];
            $run = $runner->run(OperationalHealthCheckRunner::SCOPE_FULL);
            $this->markScheduledRun($run, $scheduledAt);
            $notifications = $alerter->notify($run->refresh()->load('results'));

            $this->info(sprintf(
                'Recorded scheduled operational health check for %s: %s (%d passed, %d failed, %d warning, %d skipped).',
                $scheduledAt->format('d M Y, g:i A T'),
                $run->status,
                $run->passed_checks,
                $run->failed_checks,
                $run->warning_checks,
                $run->skipped_checks,
            ));

            if ($notifications > 0) {
                $this->warn(sprintf('Sent %d urgent operational health notification%s.', $notifications, $notifications === 1 ? '' : 's'));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{scheduled_at: Carbon, next_at: Carbon}>
     */
    private function missedSlots(CarbonInterface $now, int $graceMinutes): array
    {
        $localNow = Carbon::parse($now->toIso8601String())->timezone($this->schedule->timezone());
        $eligibleUntil = $localNow->copy()->subMinutes($graceMinutes);
        $times = $this->schedule->timesFor($localNow);
        $slots = [];

        foreach ($times as $index => $time) {
            $scheduledAt = $this->atTime($localNow, $time);

            if ($scheduledAt->greaterThan($eligibleUntil)) {
                continue;
            }

            $nextAt = isset($times[$index + 1])
                ? $this->atTime($localNow, $times[$index + 1])
                : $scheduledAt->copy()->endOfDay();

            if (! $this->slotHasRun($scheduledAt, $nextAt)) {
                $slots[] = [
                    'scheduled_at' => $scheduledAt,
                    'next_at' => $nextAt,
                ];
            }
        }

        return $slots;
    }

    private function slotHasRun(Carbon $scheduledAt, Carbon $nextAt): bool
    {
        $dayStart = $scheduledAt->copy()->startOfDay()->utc();
        $dayEnd = $scheduledAt->copy()->endOfDay()->utc();
        $slotStart = $scheduledAt->copy()->utc();
        $slotEnd = $nextAt->copy()->utc();
        $scheduledFor = $scheduledAt->toIso8601String();

        return OperationalHealthCheckRun::query()
            ->whereBetween('started_at', [$dayStart, $dayEnd])
            ->get(['started_at', 'metadata'])
            ->contains(function (OperationalHealthCheckRun $run) use ($scheduledFor, $slotStart, $slotEnd): bool {
                $metadata = $run->metadata ?? [];

                if (($metadata['scope'] ?? null) !== OperationalHealthCheckRunner::SCOPE_FULL) {
                    return false;
                }

                if (($metadata['scheduled_for'] ?? null) === $scheduledFor) {
                    return true;
                }

                if (array_key_exists('scheduled_for', $metadata)) {
                    return false;
                }

                $startedAt = $run->started_at;

                return $startedAt instanceof CarbonInterface
                    && $startedAt->greaterThanOrEqualTo($slotStart)
                    && $startedAt->lessThan($slotEnd);
            });
    }

    private function markScheduledRun(OperationalHealthCheckRun $run, Carbon $scheduledAt): void
    {
        $metadata = $run->metadata ?? [];
        $metadata['trigger'] = 'scheduled_due';
        $metadata['scheduled_for'] = $scheduledAt->toIso8601String();
        $metadata['scheduled_timezone'] = $this->schedule->timezone();

        $run->forceFill(['metadata' => $metadata])->save();
    }

    private function atTime(CarbonInterface $date, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return Carbon::parse($date->toDateString(), $this->schedule->timezone())
            ->setTime($hour, $minute);
    }

    private function integerOption(string $name, int $minimum, int $fallback): int
    {
        $value = $this->option($name);

        if (is_numeric($value) && (int) $value >= $minimum) {
            return (int) $value;
        }

        return $fallback;
    }

    private function nullableIntegerOption(string $name, int $minimum): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value >= $minimum) {
            return (int) $value;
        }

        return null;
    }
}
