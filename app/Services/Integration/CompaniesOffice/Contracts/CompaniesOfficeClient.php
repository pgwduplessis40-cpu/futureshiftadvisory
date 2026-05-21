<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesOffice\Contracts;

interface CompaniesOfficeClient
{
    /**
     * @return array<string, mixed>
     */
    public function companyProfile(string $nzbn): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function directorsForCompany(string $nzbn): array;
}
