<?php

declare(strict_types=1);

namespace App\Services\Integration\Mbie;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Mbie\Contracts\MbieClient;

final class FallbackMbieClient implements MbieClient
{
    public function __construct(
        private readonly LiveMbieClient $live,
        private readonly FakeMbieClient $fake,
    ) {}

    public function wageRates(): array
    {
        try {
            return $this->live->wageRates();
        } catch (IntegrationDisabledException) {
            return $this->fake->wageRates();
        }
    }
}
