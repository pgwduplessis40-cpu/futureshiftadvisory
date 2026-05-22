<?php

declare(strict_types=1);

namespace App\Services\Integration\Myob;

use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\Myob\Contracts\MyobClient;

final class FakeMyobClient implements MyobClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('myob-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return 'myob';
    }
}
