<?php

declare(strict_types=1);

namespace App\Services\Integration\CharitiesServices\Contracts;

interface CharitiesServicesClient
{
    /**
     * @return array<string, mixed>
     */
    public function charityProfile(string $registrationNumber): array;
}
