<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Support\Methodology\ProvidesMethodology;

final class IntegrationHealthBander implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['integration.health.banding'];
    }

    public function band(float $successRate, ?int $p95Latency, string $service = '', ?int $sampleCount = null): string
    {
        return $this->checks($successRate, $p95Latency, $service, $sampleCount)['health'];
    }

    /**
     * @return array{health:string,availability:string,response_time:string,green_success_rate:float,amber_success_rate:float,green_latency_ms:int,amber_latency_ms:int,latency_red_min_samples:int}
     */
    public function checks(float $successRate, ?int $p95Latency, string $service = '', ?int $sampleCount = null): array
    {
        $greenSuccessRate = (float) config('integrations.health.green.min_success_rate', 0.99);
        $greenP95 = (int) config('integrations.health.green.max_p95_latency_ms', 1000);
        $amberSuccessRate = (float) config('integrations.health.amber.min_success_rate', 0.95);
        $amberP95 = (int) config('integrations.health.amber.max_p95_latency_ms', 3000);
        $minimumSamples = 1;

        if ($service === 'anthropic') {
            $greenP95 = max(1, (int) config('integrations.health.anthropic.green_p95_ms', 30000));
            $amberP95 = max($greenP95, (int) config('integrations.health.anthropic.amber_p95_ms', 60000));
            $minimumSamples = max(1, (int) config('integrations.health.anthropic.latency_red_min_samples', 5));
        }

        $availability = $successRate >= $greenSuccessRate ? 'green' : ($successRate >= $amberSuccessRate ? 'amber' : 'red');
        $responseTime = $p95Latency === null ? 'unknown' : ($p95Latency <= $greenP95 ? 'green' : ($p95Latency <= $amberP95 ? 'amber' : 'red'));
        if ($service === 'anthropic' && $responseTime === 'red' && ($sampleCount ?? 0) < $minimumSamples) {
            $responseTime = 'amber';
        }

        $health = in_array('red', [$availability, $responseTime], true) ? 'red'
            : (in_array('amber', [$availability, $responseTime], true) ? 'amber' : 'green');

        return [
            'health' => $health,
            'availability' => $availability,
            'response_time' => $responseTime,
            'green_success_rate' => $greenSuccessRate,
            'amber_success_rate' => $amberSuccessRate,
            'green_latency_ms' => $greenP95,
            'amber_latency_ms' => $amberP95,
            'latency_red_min_samples' => $minimumSamples,
        ];
    }
}
