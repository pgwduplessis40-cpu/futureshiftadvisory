<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationCall;
use App\Models\IntegrationHealthSample;
use App\Services\Integration\IntegrationHealthBander;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AggregateIntegrationHealth extends Command
{
    protected $signature = 'integrations:health:aggregate
                            {--minutes=5 : Rolling window size in minutes.}
                            {--window-end= : Optional ISO-8601 window end for deterministic tests.}';

    protected $description = 'Aggregate integration call rows into Green/Amber/Red health samples.';

    public function handle(IntegrationHealthBander $bander): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $windowEndInput = $this->option('window-end');
        $windowEnd = is_string($windowEndInput) && $windowEndInput !== ''
            ? Carbon::parse($windowEndInput)
            : now();
        $windowStart = $windowEnd->copy()->subMinutes($minutes);

        $calls = IntegrationCall::query()
            ->where('occurred_at', '>=', $windowStart)
            ->where('occurred_at', '<=', $windowEnd)
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('service');

        $samples = 0;

        /** @var Collection<int, IntegrationCall> $serviceCalls */
        foreach ($calls as $service => $serviceCalls) {
            $total = $serviceCalls->count();
            if ($total === 0) {
                continue;
            }

            $successCount = $serviceCalls
                ->where('status', IntegrationCall::STATUS_SUCCESS)
                ->count();
            $successRate = $successCount / $total;
            $p95Latency = $this->p95Latency($serviceCalls);

            IntegrationHealthSample::query()->updateOrCreate(
                [
                    'service' => (string) $service,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                ],
                [
                    'success_rate' => round($successRate, 4),
                    'p95_latency_ms' => $p95Latency,
                    'health' => $bander->band($successRate, $p95Latency),
                ],
            );

            $samples++;
        }

        $this->info("Created or updated {$samples} integration health sample(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, IntegrationCall>  $calls
     */
    private function p95Latency(Collection $calls): ?int
    {
        $latencies = $calls
            ->pluck('latency_ms')
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): int => (int) $value)
            ->sort()
            ->values();

        if ($latencies->isEmpty()) {
            return null;
        }

        $index = (int) max(0, ceil($latencies->count() * 0.95) - 1);

        return $latencies[$index];
    }
}
