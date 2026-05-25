<?php

declare(strict_types=1);

namespace App\Services\Integration\EmploymentHero;

use App\Models\NzToolConnection;
use App\Services\Integration\EmploymentHero\Contracts\EmploymentHeroClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;

final class FallbackEmploymentHeroClient implements EmploymentHeroClient
{
    public function __construct(
        private readonly LiveEmploymentHeroClient $live,
        private readonly FakeEmploymentHeroClient $fake,
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
