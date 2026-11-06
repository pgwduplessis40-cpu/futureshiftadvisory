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
    /** @param FundingResult $funding */
    public function __construct(
        public ScenarioDefinition $definition,
        public array $funding,
        public ?ShockReport $revenueShock = null,
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
