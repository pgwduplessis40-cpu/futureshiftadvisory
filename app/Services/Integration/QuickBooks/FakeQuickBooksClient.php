<?php

declare(strict_types=1);

namespace App\Services\Integration\QuickBooks;

use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;

final class FakeQuickBooksClient implements QuickBooksClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('quickbooks-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return 'quickbooks';
    }
}
