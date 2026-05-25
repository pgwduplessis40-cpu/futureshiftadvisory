<?php

declare(strict_types=1);

namespace App\Services\Integration\Tradify;

use App\Models\NzToolConnection;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Tradify\Contracts\TradifyClient;

final class FallbackTradifyClient implements TradifyClient
{
    public function __construct(
        private readonly LiveTradifyClient $live,
        private readonly FakeTradifyClient $fake,
    ) {}

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            return $this->live->exchangeCodeForToken($code, $redirectUri);
        } catch (IntegrationDisabledException) {
            return $this->fake->exchangeCodeForToken($code, $redirectUri);
        }
    }

    public function businessSnapshot(NzToolConnection $connection, array $token): array
    {
        try {
            return $this->live->businessSnapshot($connection, $token);
        } catch (IntegrationDisabledException) {
            return $this->fake->businessSnapshot($connection, $token);
        }
    }

    public function revoke(NzToolConnection $connection, array $token): void
    {
        try {
            $this->live->revoke($connection, $token);
        } catch (IntegrationDisabledException) {
            $this->fake->revoke($connection, $token);
        }
    }
}
