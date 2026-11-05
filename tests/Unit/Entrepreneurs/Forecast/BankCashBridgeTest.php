<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\BankCashBridge;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class BankCashBridgeTest extends TestCase
{
    public function test_f11_named_funding_and_tax_reconcile_without_inventing_collections(): void
    {
        $opening = new BankOpening('7531', '0', '7531', '0', 'reviewed-opening', true);
        $movements = [$this->movement('named-funding', '2027-01-01', '6652', 'in', 'funding'), $this->movement('fixed', '2027-01-10', '4335.07', 'out', 'operating_payment')];
        $tax = new TaxCashPlan('scheduled', 'none_due', 'documented-tax-due', 'reviewed-gst-scope', [$this->movement('tax', '2027-01-20', '1838.18', 'out', 'tax')]);
        $result = $this->calculate($opening, $movements, $tax);
        self::assertSame('pass', $result['status']);
        self::assertSame('8009.75', $result['bank']['months'][0]['closing_cash']);
        self::assertSame('6652.00', $result['bank']['months'][0]['receipts']);
        self::assertSame('6173.25', $result['bank']['months'][0]['payments']);
        self::assertSame('478.75', (string) BigDecimal::of($result['bank']['months'][0]['closing_cash'])->minus('7531'));
        $movements[0] = new CashMovement('named-funding', 'unconfirmed-source', '2027-01-01', '6652', 'in', 'funding', 0, false);
        self::assertSame('cannot_verify', $this->calculate($opening, $movements, $tax)['status']);
    }

    public function test_gst_and_internal_restrictions_reconcile_without_double_counting_bank_cash(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/assets-tax-actuals.json'), true, flags: JSON_THROW_ON_ERROR);
        $result = $this->calculate(new BankOpening('1000', '0', '1000', '200', 'reviewed-opening', true),
            [$this->movement('receipt', '2027-01-10', '100', 'in', 'operating_receipt')],
            new TaxCashPlan('scheduled', 'scheduled', 'tax-policy-and-due-dates', 'gst-return-schedule', [
                $this->movement('tax', '2027-01-20', '20', 'out', 'tax'),
                $this->movement('gst-in', '2027-01-10', '10', 'in', 'gst', 1),
                $this->movement('gst-out', '2027-01-20', '5', 'out', 'gst', 1),
            ]), [$this->movement('reserve', '2027-01-15', '50', 'out', 'restricted_transfer'), $this->movement('release', '2027-01-25', '30', 'in', 'restricted_transfer')]);
        self::assertSame('pass', $result['status']);
        self::assertTrue($result['bridge_reconciled']);
        foreach ($fixture['expected_cash'] as $ledger => $expected) {
            self::assertSame($expected, $result[$ledger]['months'][0]['closing_cash']);
        }
        self::assertSame(['1000.00', '1100.00', '1110.00', '1110.00', '1090.00', '1085.00', '1085.00'], array_column($result['checkpoints'], 'bank'));
        self::assertSame(['200.00', '200.00', '200.00', '250.00', '250.00', '250.00', '220.00'], array_column($result['checkpoints'], 'restricted'));
        self::assertFalse($result['bank']['bank_reconciled']);
        self::assertSame('supplied_cash_projection_bridge', $result['scope']);
    }

    public function test_zero_identity_keeps_opening_checkpoint_and_capital_sale_is_bank_receipt(): void
    {
        $result = $this->calculate(new BankOpening('100', '0', '100', '0', 'review', true), [$this->movement('0', '2027-01-02', '25', 'in', 'capex')]);
        self::assertSame(['100.00', '125.00'], array_column($result['checkpoints'], 'bank'));
        self::assertSame('125.00', $result['unrestricted']['months'][0]['closing_cash']);
    }

    public function test_known_mismatch_is_failure_even_with_unknown_tax_scope(): void
    {
        $result = $this->calculate(new BankOpening('100', '5', '100', '0', 'review', true), [], new TaxCashPlan(null, null, null, null, []));
        self::assertSame('fail', $result['status']);
        self::assertContains('opening.analytical_plus_gst_does_not_equal_bank', $result['errors']);
        self::assertContains('tax.scope_or_source', $result['missing']);
        self::assertFalse($result['bridge_reconciled']);
        $cents = $this->calculate(new BankOpening('0.005', '0.005', '0.02', '0.005', 'review', true));
        self::assertSame('pass', $cents['status']);
        self::assertSame('0.01', $cents['unrestricted']['months'][0]['closing_cash']);
    }

    public function test_negative_or_excess_restrictions_fail_at_dated_checkpoint(): void
    {
        foreach ([$this->movement('release', '2027-01-02', '1', 'in', 'restricted_transfer'), $this->movement('reserve', '2027-01-02', '101', 'out', 'restricted_transfer')] as $movement) {
            $result = $this->calculate(new BankOpening('100', '0', '100', '0', 'review', true), [], null, [$movement]);
            self::assertSame('fail', $result['status']);
            self::assertSame('100.00', $result['bank']['months'][0]['closing_cash']);
        }
        $overdraft = $this->calculate(new BankOpening('-10', '0', '-10', '0', 'overdraft-review', true));
        self::assertSame('pass', $overdraft['status']);
        self::assertSame('-10.00', $overdraft['unrestricted']['lowest_cash']);
    }

    public function test_missing_source_values_dates_and_scope_remain_unverified(): void
    {
        $unknown = $this->calculate(new BankOpening(null, null, null, null, null, null));
        self::assertSame('cannot_verify', $unknown['status']);
        self::assertContains('opening.gst_bridge', $unknown['missing']);
        self::assertNull($unknown['checkpoints'][0]['bank']);
        self::assertNull($unknown['unrestricted']['lowest_cash']);
        $result = $this->calculate(new BankOpening('0', '0', '0', '0', 'unreviewed', false), [],
            new TaxCashPlan('scheduled', 'none_due', 'unconfirmed-refund', 'scope-review', [new CashMovement('refund', 'claim', null, null, 'in', 'tax', 0)]));
        self::assertSame('cannot_verify', $result['status']);
        self::assertContains('refund.refund_verification', $result['missing']);
        self::assertContains('refund.date_or_amount', $result['missing']);
        self::assertNull($result['bank']['months'][0]['closing_cash']);
    }

    public function test_tax_cash_requires_supplied_schedule_or_explicit_no_cash_scope(): void
    {
        $movement = $this->movement('tax', '2027-02-15', '20', 'out', 'tax');
        $plan = new TaxCashPlan('scheduled', 'not_applicable', 'reviewed-dates', 'non-registered-scope', [$movement]);
        $result = $this->calculate(new BankOpening('100', '0', '100', '0', 'bank', true), [], $plan);
        self::assertSame('100.00', $result['bank']['months'][0]['closing_cash']);
        self::assertSame('tax', $result['bank']['deferred'][0]->id);
        self::assertSame('scheduled', $plan->jsonSerialize()['tax_state']);
        self::assertContains('tax.schedule', (new TaxCashPlan('scheduled', 'none_due', 'review', 'review', []))->issues()['missing']);
        self::assertContains('tax.schedule_contradicts_scope', (new TaxCashPlan('none_due', 'none_due', 'review', 'review', [$movement]))->issues()['errors']);
        $refund = $this->movement('refund', '2027-01-03', '20', 'in', 'tax');
        self::assertSame([], (new TaxCashPlan('scheduled', 'none_due', 'confirmed-refund', 'review', [$refund]))->issues()['missing']);
    }

    public function test_tax_cannot_be_double_counted_as_analytical_cash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculate(new BankOpening('0', '0', '0', '0', 'review', true), [$this->movement('tax', '2027-01-01', '1', 'out', 'tax')]);
    }

    public function test_external_cash_cannot_be_entered_as_internal_restriction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculate(new BankOpening('0', '0', '0', '0', 'review', true), [], null, [$this->movement('tax', '2027-01-01', '1', 'out', 'tax')]);
    }

    private function movement(string $id, ?string $date, ?string $amount, string $direction, string $category, int $sequence = 0): CashMovement
    {
        return new CashMovement($id, 'synthetic-source', $date, $amount, $direction, $category, $sequence, true);
    }

    private function calculate(BankOpening $opening, array $movements = [], ?TaxCashPlan $tax = null, array $restrictions = []): array
    {
        return (new BankCashBridge)->calculate(new ForecastCalendar('2027-01', 1), $opening, $movements, $tax ?? new TaxCashPlan('none_due', 'none_due', 'tax-scope', 'gst-scope', []), $restrictions);
    }
}
