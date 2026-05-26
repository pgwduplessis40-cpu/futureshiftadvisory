<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesOffice;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Fixtures\FixtureRepository;

final class FakeCompaniesOfficeClient implements CompaniesOfficeClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function companyProfile(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('companies-office', $nzbn), 'stub');
    }

    public function directorsForCompany(string $nzbn): array
    {
        $profile = $this->companyProfile($nzbn);
        $directors = $profile['directors'] ?? [];

        return is_array($directors) ? array_values($directors) : [];
    }

    public function incorporatedSocietyProfile(string $identifier): array
    {
        return $this->withBadge($this->fixtures->find(
            'incorporated-societies',
            $this->normaliseIdentifier($identifier),
        ), 'stub');
    }

    public function fallbackIncorporatedSocietyProfile(string $identifier): array
    {
        return $this->withBadge($this->fixtures->find(
            'incorporated-societies',
            $this->normaliseIdentifier($identifier),
        ), 'stub_live_fallback', degraded: true);
    }

    public function fallbackCompanyProfile(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('companies-office', $nzbn), 'stub_live_fallback', degraded: true);
    }

    private function normaliseIdentifier(string $identifier): string
    {
        return strtoupper(trim($identifier));
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
