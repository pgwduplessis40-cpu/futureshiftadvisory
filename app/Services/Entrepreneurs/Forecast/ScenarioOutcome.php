<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use Brick\Math\BigDecimal;

/**
 * One scenario's result: what was requested, what it did, and what it needs.
 *
 * The ranking value is read through the definition's own named metric rather
 * than a fixed field, so a comparison cannot quietly switch measures to
 * produce a tidier ordering.
 *
 * @phpstan-type FundingResult array{status:string,lowest_cash:?string,lowest_cash_date:?string,cash_deficit:?string,reserve_target:?string,reserve_shortfall:?string,reserve_shortfall_date:?string,missing:list<string>}
 */
final readonly class ScenarioOutcome implements \JsonSerializable
{
    /**
     * `$ledger` is the scenario's own cash curve, carried so a reader can
     * reconcile the funding measures beside it against the months they came
     * from rather than taking the measures on trust. It is absent only when
     * the scenario could not be ledgered at all, which is a narrower condition
     * than the measures being unavailable: a reserve shortfall needs a policy
     * to measure against, and the months do not.
     *
     * `$profit` is the same scenario's recognised monthly profit, decomposed.
     * It is computed here rather than by whoever reads the outcome because
     * only the run holds both halves it needs: the shocked inputs the costs
     * are recognised from, and the dated movements the owner's pay is read
     * off. Absent for the same reason the curve is.
     *
     * The curve carries the positioned movements beside its months, because
     * the months are a total and a reconciliation needs the lines behind it.
     * They are this scenario's own: collection-delayed where the shock moved
     * them, so the entries reconcile to the movement the curve reports rather
     * than to the base case's.
     *
     * @param  FundingResult  $funding
     * @param  array{months:list<array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string}>,lowest_cash:?string,lowest_cash_date:?string,movements:list<CashMovement>}|null  $ledger
     * @param  array{status:string,scope:string,months:list<array<string,string|null>>,missing:list<string>}|null  $profit
     */
    public function __construct(
        public ScenarioDefinition $definition,
        public array $funding,
        public ?ShockReport $revenueShock = null,
        public ?array $ledger = null,
        public ?array $profit = null,
    ) {}

    public function status(): string
    {
        return $this->funding['status'];
    }

    /** The value this scenario is ranked by, or null when it cannot be read.
     *
     * A scenario whose inputs are incomplete has no ranking value at all. It
     * is not ranked last with a zero, which would quietly present an unknown
     * as the mildest case.
     */
    public function rankingValue(): ?BigDecimal
    {
        $raw = match ($this->definition->rankingMetric) {
            ScenarioDefinition::RANK_CASH_DEFICIT => $this->funding['cash_deficit'],
            ScenarioDefinition::RANK_RESERVE_SHORTFALL => $this->funding['reserve_shortfall'],
            ScenarioDefinition::RANK_LOWEST_CASH => $this->funding['lowest_cash'],
            default => null,
        };
        if ($raw === null) {
            return null;
        }
        $value = DriverNumbers::readSigned($raw);

        // A lower cash trough is worse, so it ranks by its negation while the
        // shortfall measures rank by magnitude directly.
        return $this->definition->rankingMetric === ScenarioDefinition::RANK_LOWEST_CASH ? $value->negated() : $value;
    }

    /** @return array{scenario:ScenarioDefinition,status:string,ranking_metric:string,ranking_value:?string,funding:FundingResult,revenue_shock:?ShockReport} */
    public function jsonSerialize(): array
    {
        $ranking = $this->rankingValue();

        return ['scenario' => $this->definition, 'status' => $this->status(),
            'ranking_metric' => $this->definition->rankingMetric,
            'ranking_value' => $ranking === null ? null : DriverNumbers::money($ranking),
            'funding' => $this->funding, 'revenue_shock' => $this->revenueShock];
    }
}
