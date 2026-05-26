<?php

declare(strict_types=1);

namespace App\Services\Integration\CharitiesServices;

use App\Services\Integration\CharitiesServices\Contracts\CharitiesServicesClient;
use App\Services\Integration\Fixtures\FixtureRepository;

final class FakeCharitiesServicesClient implements CharitiesServicesClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function charityProfile(string $registrationNumber): array
    {
        return $this->withBadge($this->fixtures->find(
            'charities-services',
            $this->normaliseRegistrationNumber($registrationNumber),
        ), 'stub');
    }

    public function fallbackCharityProfile(string $registrationNumber): array
    {
        return $this->withBadge($this->fixtures->find(
            'charities-services',
            $this->normaliseRegistrationNumber($registrationNumber),
        ), 'stub_live_fallback', degraded: true);
    }

    private function normaliseRegistrationNumber(string $registrationNumber): string
    {
        return strtoupper(trim($registrationNumber));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withBadge(array $record, string $badge, bool $degraded = false): array
    {
        return [
            ...$record,
            'source_badge' => $badge,
            'degraded' => $degraded || (bool) ($record['degraded'] ?? false),
        ];
    }
}
