<?php

declare(strict_types=1);

namespace App\Services\Integration\Xero;

use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\Xero\Contracts\XeroClient;

final class FakeXeroClient implements XeroClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('xero-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return 'xero';
    }
}
