<?php

declare(strict_types=1);

namespace App\Services\Integration\NpoFunders;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\NpoFunders\Contracts\NpoFunderSourceClient;

final class FallbackNpoFunderSourceClient implements NpoFunderSourceClient
{
    public function __construct(
        private readonly LiveNpoFunderSourceClient $live,
        private readonly FakeNpoFunderSourceClient $fake,
    ) {}

    public function fetch(string $source): array
    {
        try {
            return $this->live->fetch($source);
        } catch (IntegrationDisabledException) {
            return $this->fake->fetch($source);
        }
    }
}
