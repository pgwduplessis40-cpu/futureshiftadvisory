<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationHealthSample;
use App\Support\Methodology\ProvidesMethodology;

final class IntegrationHealthBander implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['integration.health.banding'];
    }

    public function band(float $successRate, ?int $p95Latency): string
    {
        $greenSuccessRate = (float) config('integrations.health.green.min_success_rate', 0.99);
        $greenP95 = (int) config('integrations.health.green.max_p95_latency_ms', 1000);
        $amberSuccessRate = (float) config('integrations.health.amber.min_success_rate', 0.95);
        $amberP95 = (int) config('integrations.health.amber.max_p95_latency_ms', 3000);

        if ($successRate >= $greenSuccessRate && ($p95Latency === null || $p95Latency <= $greenP95)) {
            return IntegrationHealthSample::HEALTH_GREEN;
        }

        if ($successRate >= $amberSuccessRate && ($p95Latency === null || $p95Latency <= $amberP95)) {
            return IntegrationHealthSample::HEALTH_AMBER;
        }

        return IntegrationHealthSample::HEALTH_RED;
    }
}
