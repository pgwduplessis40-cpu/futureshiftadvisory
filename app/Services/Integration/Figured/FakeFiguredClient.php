<?php

declare(strict_types=1);

namespace App\Services\Integration\Figured;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\Figured\Contracts\FiguredClient;

final class FakeFiguredClient implements FiguredClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('figured-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return AccountingConnection::PROVIDER_FIGURED;
    }
}
