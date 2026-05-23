<?php

declare(strict_types=1);

namespace App\Services\Integration\Fsp;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Fsp\Contracts\FspClient;

final class FallbackFspClient implements FspClient
{
    public function __construct(
        private readonly LiveFspClient $live,
        private readonly FakeFspClient $fake,
    ) {}

    public function lookup(string $fspNumber): array
    {
        try {
            return $this->live->lookup($fspNumber);
        } catch (IntegrationDisabledException) {
            return $this->fake->lookup($fspNumber);
        }
    }
}
