<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesEntityRoleSearch\Contracts;

interface CompaniesEntityRoleSearchClient
{
    /**
     * @return array<string, mixed>
     */
    public function search(
        string $name,
        string $roleType = 'ALL',
        bool $registeredOnly = true,
        int $page = 1,
        int $pageSize = 25,
    ): array;
}
