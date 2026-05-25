<?php

declare(strict_types=1);

namespace App\Services\Integration\EmploymentHero;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolFixtureHelpers;
use App\Services\Integration\EmploymentHero\Contracts\EmploymentHeroClient;

final class FakeEmploymentHeroClient implements EmploymentHeroClient
{
    use NzBusinessToolFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('nz-business-tools', NzToolConnection::PROVIDER_EMPLOYMENT_HERO.'.'.$key);
    }

    protected function providerName(): string
    {
        return NzToolConnection::PROVIDER_EMPLOYMENT_HERO;
    }
}
