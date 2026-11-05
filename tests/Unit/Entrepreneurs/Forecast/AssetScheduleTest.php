<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\AssetSchedule;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use PHPUnit\Framework\TestCase;

final class AssetScheduleTest extends TestCase
{
    public function test_purchase_beyond_initial_year_remains_in_schedule_and_has_no_early_depreciation(): void
    {
        $result = $this->calculate($this->asset(paidOn: '2028-02-01', recognitionStartOn: '2028-02-01'), 14);
        self::assertSame('2028-02-01', $result['cash_movements'][0]->date);
        self::assertSame('0.00', $result['months'][11]['depreciation']);
        self::assertSame('0.00', $result['months'][11]['capital_prepayment']);
        self::assertSame('100.00', $result['months'][13]['depreciation']);
        self::assertSame('1100.00', $result['months'][13]['closing_carrying_value']);
    }

    public function test_purchase_cash_prepayment_and_book_depreciation_are_separate(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/Forecast/assets-tax-actuals.json'), true, flags: JSON_THROW_ON_ERROR);
        $result = $this->calculate($this->asset(cost: $fixture['asset']['cost'], paidOn: $fixture['asset']['paid_on'], recognitionStartOn: $fixture['asset']['start_on'], lifeMonths: $fixture['asset']['life_months']), 14);
        self::assertSame('pass', $result['status']);
        self::assertSame($fixture['expected_asset']['january_cash'], $result['cash_movements'][0]->amount);
        self::assertSame('capex', $result['cash_movements'][0]->category);
        self::assertSame('0.00', $result['months'][0]['depreciation']);
        self::assertSame($fixture['expected_asset']['january_prepayment'], $result['months'][0]['capital_prepayment']);
        self::assertSame($fixture['expected_asset']['february_depreciation'], $result['months'][1]['depreciation']);
        self::assertSame($fixture['expected_asset']['february_carrying'], $result['months'][1]['closing_carrying_value']);
        self::assertSame('0.00', $result['months'][1]['capital_prepayment']);
        self::assertSame('0.00', $result['months'][12]['closing_carrying_value']);
        self::assertSame('0.00', $result['months'][13]['depreciation']);
        self::assertTrue($result['review_required']);
        self::assertNull($result['prior_payment']);
        self::assertSame([], $fixture['enabled_rule_ids']);
        self::assertSame('1200', $result['input']->jsonSerialize()['cost']);
    }

    public function test_threshold_requests_review_but_never_selects_classification(): void
    {
        $expense = $this->calculate($this->asset(classification: 'expense', lifeMonths: null, residual: null, method: null));
        self::assertSame('pass', $expense['status']);
        self::assertSame('1200.00', $expense['months'][1]['expense']);
        self::assertSame('operating_payment', $expense['cash_movements'][0]->category);
        self::assertSame('0.00', $expense['months'][1]['depreciation']);
        $small = $this->calculate($this->asset(cost: '900', reviewed: false));
        self::assertSame('pass', $small['status']);
        self::assertFalse($small['review_required']);
        self::assertSame('75.00', $small['months'][1]['depreciation']);
        $unknown = $this->calculate($this->asset(classification: null, reviewed: false));
        self::assertSame('cannot_verify', $unknown['status']);
        self::assertNull($unknown['cash_movements']);
        self::assertContains('classification_review', $unknown['missing']);
    }

    public function test_existing_assets_require_supplied_accumulated_depreciation_and_do_not_replay_cash(): void
    {
        $asset = $this->asset(paidOn: '2026-07-01', recognitionStartOn: '2026-07-01', openingDepreciation: '600.004');
        $result = $this->calculate($asset, 7);
        self::assertSame([], $result['cash_movements']);
        self::assertSame('2026-07-01', $result['prior_payment']->date);
        self::assertSame(['100.00', '100.00', '100.00', '100.00', '100.00', '100.00', '0.00'], array_column($result['months'], 'depreciation'));
        self::assertSame('0.00', $result['months'][6]['closing_carrying_value']);
        $unknown = $this->calculate($this->asset(recognitionStartOn: '2026-07-01'));
        self::assertSame('cannot_verify', $unknown['status']);
        self::assertContains('opening_depreciation', $unknown['missing']);
        self::assertNull($unknown['months'][0]['depreciation']);
    }

    public function test_cent_residual_is_final_and_value_never_falls_below_residual(): void
    {
        $result = $this->calculate($this->asset(cost: '10', residual: '1', lifeMonths: 7, recognitionStartOn: '2027-01-01'), 8);
        self::assertSame(['1.28', '1.28', '1.28', '1.28', '1.28', '1.28', '1.32', '0.00'], array_column($result['months'], 'depreciation'));
        self::assertSame('1.00', $result['months'][7]['closing_carrying_value']);
    }

    public function test_missing_policy_inputs_remain_unknown_and_known_errors_win(): void
    {
        $unknown = $this->calculate($this->asset(cost: null, paidOn: null, recognitionStartOn: null, policySource: null, lifeMonths: null, residual: null, method: null));
        self::assertSame('cannot_verify', $unknown['status']);
        self::assertNull($unknown['review_required']);
        self::assertNull($unknown['months'][0]['depreciation']);
        self::assertContains('policy_source', $unknown['missing']);
        foreach ([$this->asset(residual: '1201', policySource: null), $this->asset(recognitionStartOn: '2026-01-01', openingDepreciation: '1201'), $this->asset(recognitionStartOn: '2026-01-01', openingDepreciation: '1000')] as $bad) {
            self::assertSame('fail', $this->calculate($bad)['status']);
        }
        $paidLater = $this->calculate($this->asset(paidOn: '2027-02-01'));
        self::assertSame('0.00', $paidLater['months'][0]['capital_prepayment']);
        $paidUnknown = $this->calculate($this->asset(paidOn: null));
        self::assertNull($paidUnknown['months'][0]['capital_prepayment']);
        self::assertSame('cannot_verify', $paidUnknown['status']);
        $exhausted = $this->calculate($this->asset(recognitionStartOn: '2026-01-01', openingDepreciation: '1200'));
        self::assertSame('pass', $exhausted['status']);
        self::assertSame('0.00', $exhausted['months'][0]['depreciation']);
    }

    private function asset(?string $cost = '1200', ?string $classification = 'capital', ?string $policySource = 'reviewed-book-policy', ?bool $reviewed = true,
        ?string $paidOn = '2027-01-20', ?string $recognitionStartOn = '2027-02-15', ?int $lifeMonths = 12, ?string $residual = '0',
        ?string $method = 'straight_line_full_start_month', ?string $openingDepreciation = null): AssetInput
    {
        return new AssetInput('asset', 'synthetic-quote', $cost, $classification, $policySource, $reviewed, $paidOn, $recognitionStartOn, $lifeMonths, $residual, $method, $openingDepreciation);
    }

    private function calculate(AssetInput $asset, int $months = 2): array
    {
        return (new AssetSchedule)->calculate(new ForecastCalendar('2027-01', $months), $asset, 0);
    }
}
