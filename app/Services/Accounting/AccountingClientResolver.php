<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingConnection;
use App\Services\Integration\Figured\Contracts\FiguredClient;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;
use App\Services\Integration\Sage\Contracts\SageClient;
use App\Services\Integration\Workflowmax\Contracts\WorkflowmaxClient;
use App\Services\Integration\Xero\Contracts\XeroClient;
use InvalidArgumentException;

final class AccountingClientResolver
{
    public function __construct(
        private readonly XeroClient $xero,
        private readonly MyobClient $myob,
        private readonly QuickBooksClient $quickBooks,
        private readonly SageClient $sage,
        private readonly FiguredClient $figured,
        private readonly WorkflowmaxClient $workflowmax,
    ) {}

    public function client(string $provider): XeroClient|MyobClient|QuickBooksClient|SageClient|FiguredClient|WorkflowmaxClient
    {
        return match ($provider) {
            AccountingConnection::PROVIDER_XERO => $this->xero,
            AccountingConnection::PROVIDER_MYOB => $this->myob,
            AccountingConnection::PROVIDER_QUICKBOOKS => $this->quickBooks,
            AccountingConnection::PROVIDER_SAGE => $this->sage,
            AccountingConnection::PROVIDER_FIGURED => $this->figured,
            AccountingConnection::PROVIDER_WORKFLOWMAX => $this->workflowmax,
            default => throw new InvalidArgumentException("Unsupported accounting provider [{$provider}]."),
        };
    }
}
