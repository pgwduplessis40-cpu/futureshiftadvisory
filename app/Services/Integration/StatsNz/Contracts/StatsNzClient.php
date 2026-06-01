<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz\Contracts;

interface StatsNzClient
{
    /**
     * Macro indicators are governed manual snapshots from reference data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function industryBenchmarks(): array;
}
