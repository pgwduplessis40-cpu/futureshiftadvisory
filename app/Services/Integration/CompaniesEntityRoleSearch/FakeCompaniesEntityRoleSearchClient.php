<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesEntityRoleSearch;

use App\Services\Integration\CompaniesEntityRoleSearch\Contracts\CompaniesEntityRoleSearchClient;
use App\Services\Integration\Fixtures\FixtureRepository;

final class FakeCompaniesEntityRoleSearchClient implements CompaniesEntityRoleSearchClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function search(
        string $name,
        string $roleType = 'ALL',
        bool $registeredOnly = true,
        int $page = 1,
        int $pageSize = 25,
    ): array {
        return $this->withBadge($this->fixtures->find('companies-entity-role-search', $this->key($name)), 'stub');
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackSearch(string $name): array
    {
        return $this->withBadge($this->fixtures->find('companies-entity-role-search', $this->key($name)), 'stub_live_fallback', degraded: true);
    }

    private function key(string $name): string
    {
        $key = strtolower(trim($name));

        return $key === '' ? 'default' : $key;
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
