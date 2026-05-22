<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz\Contracts;

interface StatsNzClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array;
}
