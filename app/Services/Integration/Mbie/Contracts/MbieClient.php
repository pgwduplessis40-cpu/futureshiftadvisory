<?php

declare(strict_types=1);

namespace App\Services\Integration\Mbie\Contracts;

interface MbieClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function wageRates(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function valuationMultiples(): array;
}
