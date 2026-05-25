<?php

declare(strict_types=1);

namespace App\Services\Integration\Tradify;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolFixtureHelpers;
use App\Services\Integration\Tradify\Contracts\TradifyClient;

final class FakeTradifyClient implements TradifyClient
{
    use NzBusinessToolFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('nz-business-tools', NzToolConnection::PROVIDER_TRADIFY.'.'.$key);
    }

    protected function providerName(): string
    {
        return NzToolConnection::PROVIDER_TRADIFY;
    }
}
