<?php

declare(strict_types=1);

namespace Tests\Unit\Accounting;

use App\Models\AccountingConnection;
use Tests\TestCase;

final class AccountingConnectionProviderLabelsTest extends TestCase
{
    public function test_applicable_provider_labels_include_connected_and_live_ready_providers_only(): void
    {
        $labels = AccountingConnection::applicableProviderLabels(
            [AccountingConnection::PROVIDER_XERO],
            fn (string $provider): bool => $provider === AccountingConnection::PROVIDER_QUICKBOOKS,
        );

        $this->assertSame([
            AccountingConnection::PROVIDER_XERO => 'Xero',
            AccountingConnection::PROVIDER_QUICKBOOKS => 'QuickBooks',
        ], $labels);
    }

    public function test_no_provider_labels_are_applicable_without_connection_or_live_readiness(): void
    {
        $this->assertSame(
            [],
            AccountingConnection::applicableProviderLabels([], fn (): bool => false),
        );
    }
}
