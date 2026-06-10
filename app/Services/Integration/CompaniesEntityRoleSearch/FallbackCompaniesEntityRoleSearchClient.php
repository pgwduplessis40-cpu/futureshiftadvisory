<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesEntityRoleSearch;

use App\Services\Integration\CompaniesEntityRoleSearch\Contracts\CompaniesEntityRoleSearchClient;
use Throwable;

final class FallbackCompaniesEntityRoleSearchClient implements CompaniesEntityRoleSearchClient
{
    public function __construct(
        private readonly LiveCompaniesEntityRoleSearchClient $live,
        private readonly FakeCompaniesEntityRoleSearchClient $fake,
    ) {}

    public function search(
        string $name,
        string $roleType = 'ALL',
        bool $registeredOnly = true,
        int $page = 1,
        int $pageSize = 25,
    ): array {
        try {
            return $this->live->search($name, $roleType, $registeredOnly, $page, $pageSize);
        } catch (Throwable) {
            return $this->fake->search($name, $roleType, $registeredOnly, $page, $pageSize);
        }
    }
}
