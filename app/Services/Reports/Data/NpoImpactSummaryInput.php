<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Client-authored narrative and fact-checkable metric maps for an Impact Summary.
 *
 * @phpstan-type MetricMap array<string, float|int>
 */
final readonly class NpoImpactSummaryInput
{
    /**
     * @param  MetricMap|null  $metrics
     * @param  MetricMap|null  $platformMetrics
     */
    public function __construct(
        public string $summary,
        public ?array $metrics = null,
        public ?array $platformMetrics = null,
    ) {}

    public static function draft(): self
    {
        return new self(
            summary: 'Impact Summary draft pending client narrative.',
            metrics: [],
            platformMetrics: [],
        );
    }
}
