<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\AccountingNumbers;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entrepreneurs\Forecast\ActualsFixture as F;

final class AccountingContractsTest extends TestCase
{
    #[DataProvider('invalidContracts')]
    public function test_invalid_contracts_are_rejected(\Closure $build): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $build();
    }

    public static function invalidContracts(): array
    {
        $asset = ['id' => 'asset', 'source' => 'quote', 'cost' => '100', 'classification' => 'capital', 'policySource' => 'policy',
            'reviewed' => true, 'paidOn' => '2027-01-01', 'recognitionStartOn' => '2027-01-01', 'lifeMonths' => 12, 'residual' => '0', 'method' => 'straight_line_full_start_month'];

        return [
            [fn () => new AssetInput(...array_replace($asset, ['id' => '']))],
            [fn () => new AssetInput(...array_replace($asset, ['classification' => 'automatic']))],
            [fn () => new AssetInput(...array_replace($asset, ['lifeMonths' => 0]))],
            [fn () => new AssetInput(...array_replace($asset, ['lifeMonths' => 1201]))],
            [fn () => new AssetInput(...array_replace($asset, ['method' => 'guessed']))],
            [fn () => new TaxPolicy('entity', '101', true, 'policy', 'evidence', 'v1')],
            [fn () => new TaxPeriod('2027-02-01', '2027-01-31', '0', '0')],
            [fn () => new TaxCashPlan('automatic', 'none_due', 'policy', 'scope', [])],
            [fn () => new TaxCashPlan('none_due', 'none_due', 'policy', 'scope', [F::movement('sale', '2027-01-01', '1', 'in', 'operating_receipt')])],
            [fn () => F::source(changes: ['provider' => 'unknown-provider'])],
            [fn () => F::source(changes: ['accountMappings' => ['profit.revenue' => ['']]])],
            [fn () => F::month(changes: ['state' => 'assumed-zero'])],
            [fn () => AccountingNumbers::signed('--1')],
            [fn () => AccountingNumbers::signed('1e3')],
            [fn () => new CashMovement('wrong', 'source', '2027-01-01', '1', 'in', 'owner_pay', 0)],
        ];
    }

    public function test_signed_zero_unknown_and_serialized_policy_remain_distinct(): void
    {
        self::assertSame('-10.01', AccountingNumbers::money(AccountingNumbers::signed('-10.005')));
        self::assertSame('0.00', AccountingNumbers::money(AccountingNumbers::signed('-0')));
        self::assertNull(AccountingNumbers::cents(null));
        self::assertNull(AccountingNumbers::same(null, '0'));
        self::assertTrue(AccountingNumbers::same('1.004', '1'));
        $period = new TaxPeriod('2027-01-01', '2027-12-31', '-100', '20');
        self::assertSame('-100', $period->jsonSerialize()['accounting_profit']);
        self::assertSame('20', $period->jsonSerialize()['tax_adjustments']);
        $month = F::month();
        self::assertInstanceOf(ActualMonth::class, $month);
        self::assertSame('100', $month->closing->jsonSerialize()['bank_cash']);
        self::assertSame('manual', $month->source->jsonSerialize()['provider']);
    }
}
