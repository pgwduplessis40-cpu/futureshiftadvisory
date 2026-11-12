<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\MonthlyTaxChain;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * The monthly tax chain: what each month earned, what it owes, what it carries
 * forward and what it pays.
 *
 * MI-04 checks the base case's chain and SS-01 re-runs the whole tax rule
 * against each scenario's, so both are stated from here. A scenario's chain is
 * genuinely its own: a downside earns less, so it owes less and carries more
 * loss forward, and that difference is the point of running it.
 *
 * The profit the chain starts from is supplied rather than derived, because
 * only the caller knows whose profit it is - the reconstruction's own
 * decomposition, or a scenario's recognised from shocked inputs.
 */
final class TaxChainFacts implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /**
     * @param  list<array<string,string|null>>  $profitMonths  The decomposition this chain starts from.
     * @return list<Fact>
     */
    public function capture(SourceRecords $source, ForecastModelInputs $inputs, array $profitMonths, string $scenario = 'base'): array
    {
        $policy = $inputs->taxPolicy;
        if ($policy === null) {
            return [];
        }
        $at = [$source->reference('forecast_model.drivers.tax_policy')];
        $reviewed = [];
        foreach ($inputs->actualMonths as $index => $month) {
            // A month nobody reconciled is not a recorded profit. Reading one
            // off an unreviewed month would put an unchecked figure at the
            // head of a chain every later month depends on.
            if ($month->source->reconciled === true && $month->profit->profitBeforeTax !== null) {
                $reviewed[$month->period] = [$month->profit->profitBeforeTax, $index];
            }
        }
        // A reviewed month's accounts outrank the decomposition for the same
        // month. The decomposition is what the model implies; the accounts are
        // what happened, and where both exist the second is the better figure.
        // That holds for a scenario too: a shock changes what the model
        // implies about the future, never what the accounts recorded.
        $profits = [];
        foreach ($profitMonths as $month) {
            if (($month['profit'] ?? null) !== null) {
                $profits[(string) $month['period']] = (string) $month['profit'];
            }
        }
        foreach ($reviewed as $period => $row) {
            $profits[$period] = $row[0];
        }

        $chain = (new MonthlyTaxChain)->calculate(
            $inputs->calendar->periods,
            $profits,
            $this->adjustments($inputs),
            $policy->ratePercent,
            $inputs->openingEligibleLosses,
            $policy->lossesEligible,
        );

        $facts = [];
        foreach ($chain['months'] as $offset => $month) {
            $period = $month['period'];
            if ($month['profit'] === null) {
                continue;
            }
            $label = 'month:'.($offset + 1);
            // The basis says which of the two a month's profit is, because a
            // figure read from reviewed accounts and one implied by the model
            // carry different weight and a reader has to be able to tell.
            $where = isset($reviewed[$period])
                ? [$source->reference('forecast_model.actuals.months.'.$reviewed[$period][1])]
                : [$source->reference('forecast_model.drivers')];
            $facts[] = new Fact('tax.profit', $month['profit'], period: $label, scenario: $scenario, unit: 'NZD',
                basis: isset($reviewed[$period]) ? 'reviewed_actual_profit_before_tax' : 'recognised_profit_before_tax',
                confirmed: isset($reviewed[$period]), sources: $where);
            $facts[] = new Fact('tax.adjustment', $month['adjustment'], period: $label, scenario: $scenario,
                unit: 'NZD', confirmed: true, sources: [$source->reference('forecast_model.drivers.tax_periods')]);
            // Withheld together. Expense without the closing balance behind it
            // would let a month reconcile while the losses it consumed went
            // unstated, which is the half of the chain that carries forward.
            if ($month['expense'] !== null && $month['loss_closing'] !== null) {
                $facts[] = new Fact('tax.expense', $month['expense'], period: $label, scenario: $scenario,
                    unit: 'NZD', confirmed: true, sources: [...$at, ...$where]);
                $facts[] = new Fact('tax.closing_losses', $month['loss_closing'], period: $label, scenario: $scenario,
                    unit: 'NZD', confirmed: true, sources: [...$at, ...$where]);
                $facts = [...$facts, ...$this->cash($source, $inputs, $period, $label, $month['expense'], $scenario)];
            }
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function cash(SourceRecords $source, ForecastModelInputs $inputs, string $period, string $label,
        string $expense, string $scenario): array
    {
        $plan = $inputs->taxCashPlan;
        // A plan whose scope nobody has stated says nothing about any month.
        // An empty schedule is equally a business with no tax due and one
        // nobody has worked out, and only the scope state tells them apart.
        if ($plan?->taxState === null) {
            return [];
        }
        $at = [$source->reference('forecast_model.drivers.tax_cash_plan')];
        $paid = BigDecimal::zero();
        $on = null;
        foreach ($plan->movements as $movement) {
            if ($movement->category !== 'tax' || $movement->date === null || substr($movement->date, 0, 7) !== $period) {
                continue;
            }
            if ($movement->direction === 'in' || $movement->amount === null) {
                return [];
            }
            $paid = $paid->plus($movement->amount);
            // The month's own payment date, taken as the last one in it. The
            // rule requires a real date on the payment, and a month settled in
            // instalments is settled when the last of them lands.
            $on = $on === null || $movement->date > $on ? $movement->date : $on;
        }
        // A month with nothing scheduled paid nothing, which is a position
        // rather than a gap: the expense it recognised is carried as tax
        // payable until a later month settles it. A nil payment has no date
        // of its own, so it is dated to the month it belongs to.
        $on ??= $period.'-01';

        // Both at cents, because they are currency and are read beside the
        // expense they reconcile to. A sum of amounts typed without decimals
        // would otherwise state a payment of "700" against an expense of
        // "700.00", which is the same figure written two ways.
        return [
            new Fact('tax.payment', Checks::money($paid), period: $label, scenario: $scenario, unit: 'NZD',
                asOf: $on, confirmed: true, sources: $at),
            new Fact('tax.payable_movement', Checks::money(BigDecimal::of($expense)->minus($paid)), period: $label,
                scenario: $scenario, unit: 'NZD', confirmed: true, sources: $at),
        ];
    }

    /** @return array<string,string> */
    private function adjustments(ForecastModelInputs $inputs): array
    {
        $adjustments = [];
        foreach ($inputs->calendar->periods as $period) {
            foreach ($inputs->taxPeriods as $tax) {
                $opens = substr($tax->startOn, 0, 7);
                $closes = substr($tax->endOn, 0, 7);
                if ($period < $opens || $period > $closes) {
                    continue;
                }
                $adjustments[$period] = $period === $closes ? ($tax->taxAdjustments ?? '0.00') : '0.00';
                break;
            }
        }

        return $adjustments;
    }
}
