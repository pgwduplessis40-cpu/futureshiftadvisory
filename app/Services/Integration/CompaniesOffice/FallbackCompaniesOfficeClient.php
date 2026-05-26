<?php

declare(strict_types=1);

namespace App\Services\Integration\CompaniesOffice;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Exceptions\IntegrationDisabledException;

final class FallbackCompaniesOfficeClient implements CompaniesOfficeClient
{
    public function __construct(
        private readonly LiveCompaniesOfficeClient $live,
        private readonly FakeCompaniesOfficeClient $fake,
    ) {}

    public function companyProfile(string $nzbn): array
    {
        try {
            return $this->live->companyProfile($nzbn);
        } catch (IntegrationDisabledException) {
            return $this->fake->companyProfile($nzbn);
        }
    }

    public function directorsForCompany(string $nzbn): array
    {
        try {
            return $this->live->directorsForCompany($nzbn);
        } catch (IntegrationDisabledException) {
            return $this->fake->directorsForCompany($nzbn);
        }
    }

    public function incorporatedSocietyProfile(string $identifier): array
    {
        try {
            return $this->live->incorporatedSocietyProfile($identifier);
        } catch (IntegrationDisabledException) {
            return $this->fake->incorporatedSocietyProfile($identifier);
        }
    }
}
