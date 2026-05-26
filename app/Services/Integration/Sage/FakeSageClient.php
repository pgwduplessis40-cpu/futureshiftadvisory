<?php

declare(strict_types=1);

namespace App\Services\Integration\Sage;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\Sage\Contracts\SageClient;

final class FakeSageClient implements SageClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('sage-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return AccountingConnection::PROVIDER_SAGE;
    }
}
