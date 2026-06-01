<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;

final class FallbackStatsNzClient implements StatsNzClient
{
    public function __construct(
        private readonly LiveStatsNzClient $live,
        private readonly FakeStatsNzClient $fake,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        return $this->live->indicators();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function industryBenchmarks(): array
    {
        try {
            return $this->live->industryBenchmarks();
        } catch (IntegrationDisabledException) {
            return $this->fake->fallbackIndustryBenchmarks();
        }
    }
}
