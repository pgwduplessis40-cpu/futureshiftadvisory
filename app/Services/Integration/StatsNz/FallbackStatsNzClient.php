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

    public function indicators(): array
    {
        try {
            return $this->live->indicators();
        } catch (IntegrationDisabledException) {
            return $this->fake->indicators();
        }
    }
}
