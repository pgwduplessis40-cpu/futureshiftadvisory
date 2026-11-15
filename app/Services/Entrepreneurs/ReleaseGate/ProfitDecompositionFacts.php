<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Monthly profit, decomposed the way SS-01 reconciles it.
 *
 * The rule checks profit against revenue less variable costs, fixed costs,
 * depreciation and interest. The model keeps nine parts rather than five, so
 * three of them are grouped and the grouping is stated on the fact rather than
 * left for a reader to infer: direct costs, one-off operating costs, assets
 * charged to expense and deductible owner pay are the costs of trading that
 * are neither fixed, depreciation, nor financing. Revenue is likewise the
 * recurring streams plus the one-off trading income recognised in the month.
 *
 * The grouping is arithmetically closed on purpose. These five sum back to the
 * same profit the model computed from its own nine, so the rule's
 * reconciliation tests the decomposition rather than a rounding of it.
 *
 * A month the model could not complete publishes none of its parts. A
 * decomposition missing one line still adds up to something, and that
 * something would reconcile.
 *
 * The base case and every scenario state this the same way. SS-01 reads the
 * base case as a scenario of its own, so a decomposition emitted only for the
 * shocked ones would leave the base case reporting its profit unestablished
 * while every scenario derived from it reconciled.
 */
final class ProfitDecompositionFacts implements ProvidesMethodology
{
    /**
     * What each line of the published decomposition contains.
     *
     * Stated on the fact because three of the five group parts the model keeps
     * separately, and a reader comparing them against the model's own figures
     * needs to know which went where.
     *
     * @var array<string,string>
     */
    private const BASIS = [
        'revenue' => 'recurring_streams_plus_one_off_trading_income',
        'variable_costs' => 'direct_costs_one_off_operating_costs_asset_expense_and_deductible_owner_pay',
        'fixed_costs' => 'recognised_recurring_overheads',
        'depreciation' => 'asset_schedule_depreciation',
        'interest' => 'financing_charges_recognised_in_the_month',
    ];

    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /**
     * @param  array{status:string,scope:string,months:list<array<string,string|null>>,missing:list<string>}|null  $profit
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    public function capture(ForecastCalendar $calendar, ?array $profit, array $at, string $scenario = 'base'): array
    {
        if ($profit === null) {
            return [];
        }
        $offsets = array_flip($calendar->periods);
        $facts = [];
        foreach ($profit['months'] as $month) {
            $period = (string) $month['period'];
            if (! isset($offsets[$period]) || $month['profit'] === null) {
                continue;
            }
            $label = 'month:'.($offsets[$period] + 1);
            $parts = [
                'revenue' => $this->sum($month, ['revenue', 'one_off_income']),
                'variable_costs' => $this->sum($month, ['direct_costs', 'one_off_costs', 'asset_expense', 'owner_pay']),
                'fixed_costs' => $this->sum($month, ['fixed_costs']),
                'depreciation' => $this->sum($month, ['depreciation']),
                'interest' => $this->sum($month, ['interest']),
            ];
            if (in_array(null, $parts, true)) {
                continue;
            }
            foreach ($parts as $name => $value) {
                $facts[] = new Fact('scenario.'.$name, $value, period: $label, scenario: $scenario, unit: 'NZD',
                    basis: self::BASIS[$name], confirmed: true, sources: $at);
            }
            $facts[] = new Fact('scenario.profit', $month['profit'], period: $label, scenario: $scenario,
                unit: 'NZD', basis: $profit['scope'], confirmed: true, sources: $at);
        }

        return $facts;
    }

    /**
     * The named parts added, or none when one of them is unstated.
     *
     * @param  array<string,string|null>  $month
     * @param  list<string>  $names
     */
    private function sum(array $month, array $names): ?string
    {
        $total = BigDecimal::zero();
        foreach ($names as $name) {
            if (($month[$name] ?? null) === null) {
                return null;
            }
            $total = $total->plus((string) $month[$name]);
        }

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }
}
