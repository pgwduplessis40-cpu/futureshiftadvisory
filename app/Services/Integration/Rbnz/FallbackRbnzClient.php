<?php

declare(strict_types=1);

namespace App\Services\Integration\Rbnz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;

final class FallbackRbnzClient implements RbnzClient
{
    public function __construct(
        private readonly LiveRbnzClient $live,
        private readonly FakeRbnzClient $fake,
    ) {}

    public function ocr(): array
    {
        try {
            return $this->live->ocr();
        } catch (IntegrationDisabledException) {
            return $this->fake->ocr();
        }
    }

    public function exchangeRates(): array
    {
        try {
            return $this->live->exchangeRates();
        } catch (IntegrationDisabledException) {
            return $this->fake->exchangeRates();
        }
    }
}
