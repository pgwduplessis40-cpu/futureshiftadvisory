<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationHealthAlert;
use App\Models\IntegrationHealthSample;
use App\Models\User;
use App\Notifications\IntegrationHealthStuckRedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class AlertStuckRedIntegrations extends Command
{
    protected $signature = 'integrations:health:alert-stuck-red
                            {--minutes=30 : Red duration before alerting.}';

    protected $description = 'Notify super-admins once when an integration remains red beyond the configured threshold.';

    public function handle(): int
    {
        $thresholdSeconds = max(1, (int) $this->option('minutes')) * 60;
        $recipients = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();
        $alertsCreated = 0;

        IntegrationHealthSample::query()
            ->orderBy('service')
            ->orderBy('window_end')
            ->get()
            ->groupBy('service')
            ->each(function (Collection $samples, string $service) use ($thresholdSeconds, $recipients, &$alertsCreated): void {
                $redRun = $this->currentRedRun($samples);
                if ($redRun->isEmpty()) {
                    return;
                }

                /** @var IntegrationHealthSample $latest */
                $latest = $redRun->first();
                /** @var IntegrationHealthSample $oldest */
                $oldest = $redRun->last();

                $stuckStartedAt = $oldest->window_start ?? $oldest->window_end;
                $lastRedWindowEnd = $latest->window_end;

                if ($stuckStartedAt === null || $lastRedWindowEnd === null) {
                    return;
                }

                if ($stuckStartedAt->diffInSeconds($lastRedWindowEnd) < $thresholdSeconds) {
                    return;
                }

                $alert = IntegrationHealthAlert::query()->firstOrCreate(
                    [
                        'service' => $service,
                        'stuck_started_at' => $stuckStartedAt,
                    ],
                    [
                        'last_red_window_end' => $lastRedWindowEnd,
                        'notified_at' => now(),
                    ],
                );

                if (! $alert->wasRecentlyCreated) {
                    $alert->forceFill([
                        'last_red_window_end' => $lastRedWindowEnd,
                    ])->save();

                    return;
                }

                Notification::send($recipients, new IntegrationHealthStuckRedNotification($alert));
                $alertsCreated++;
            });

        $this->info("Created {$alertsCreated} stuck-red integration alert(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, IntegrationHealthSample>  $samples
     * @return Collection<int, IntegrationHealthSample>
     */
    private function currentRedRun(Collection $samples): Collection
    {
        $ordered = $samples
            ->sortByDesc(fn (IntegrationHealthSample $sample) => $sample->window_end?->getTimestamp() ?? 0)
            ->values();

        /** @var IntegrationHealthSample|null $latest */
        $latest = $ordered->first();
        if (! $latest instanceof IntegrationHealthSample || $latest->health !== IntegrationHealthSample::HEALTH_RED) {
            return collect();
        }

        $run = collect();
        foreach ($ordered as $sample) {
            if (! $sample instanceof IntegrationHealthSample || $sample->health !== IntegrationHealthSample::HEALTH_RED) {
                break;
            }

            $run->push($sample);
        }

        return $run;
    }
}
