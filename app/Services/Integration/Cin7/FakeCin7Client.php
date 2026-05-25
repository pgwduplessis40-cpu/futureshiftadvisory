<?php

declare(strict_types=1);

namespace App\Services\Integration\Cin7;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolFixtureHelpers;
use App\Services\Integration\Cin7\Contracts\Cin7Client;

final class FakeCin7Client implements Cin7Client
{
    use NzBusinessToolFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('nz-business-tools', NzToolConnection::PROVIDER_CIN7.'.'.$key);
    }

    protected function providerName(): string
    {
        return NzToolConnection::PROVIDER_CIN7;
    }
}
