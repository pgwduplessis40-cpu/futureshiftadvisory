<?php

declare(strict_types=1);

namespace App\Services\Integration\CharitiesServices;

use App\Services\Integration\CharitiesServices\Contracts\CharitiesServicesClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;

final class FallbackCharitiesServicesClient implements CharitiesServicesClient
{
    public function __construct(
        private readonly LiveCharitiesServicesClient $live,
        private readonly FakeCharitiesServicesClient $fake,
    ) {}

    public function charityProfile(string $registrationNumber): array
    {
        try {
            return $this->live->charityProfile($registrationNumber);
        } catch (IntegrationDisabledException) {
            return $this->fake->charityProfile($registrationNumber);
        }
    }
}
