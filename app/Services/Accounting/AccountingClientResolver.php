<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;
use App\Services\Integration\Xero\Contracts\XeroClient;
use InvalidArgumentException;

final class AccountingClientResolver
{
    public function __construct(
        private readonly XeroClient $xero,
        private readonly MyobClient $myob,
        private readonly QuickBooksClient $quickBooks,
    ) {}

    public function client(string $provider): XeroClient|MyobClient|QuickBooksClient
    {
        return match ($provider) {
            AccountingConnection::PROVIDER_XERO => $this->xero,
            AccountingConnection::PROVIDER_MYOB => $this->myob,
            AccountingConnection::PROVIDER_QUICKBOOKS => $this->quickBooks,
            default => throw new InvalidArgumentException("Unsupported accounting provider [{$provider}]."),
        };
    }
}
