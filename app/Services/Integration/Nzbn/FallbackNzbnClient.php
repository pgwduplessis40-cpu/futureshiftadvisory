<?php

declare(strict_types=1);

namespace App\Services\Integration\Nzbn;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;

final class FallbackNzbnClient implements NzbnClient
{
    public function __construct(
        private readonly LiveNzbnClient $live,
        private readonly FakeNzbnClient $fake,
    ) {}

    public function lookupByNzbn(string $nzbn): array
    {
        try {
            return $this->live->lookupByNzbn($nzbn);
        } catch (IntegrationDisabledException) {
            return $this->fake->lookupByNzbn($nzbn);
        }
    }
}
