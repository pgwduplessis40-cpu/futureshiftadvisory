<?php

declare(strict_types=1);

namespace App\Services\Integration\Rbnz\Contracts;

interface RbnzClient
{
    /**
     * @return array<string, mixed>
     */
    public function ocr(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exchangeRates(): array;
}
