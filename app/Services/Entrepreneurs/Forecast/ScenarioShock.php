<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use Brick\Math\BigDecimal;

/**
 * One typed shock within a scenario definition.
 *
 * Shocks are declared, never applied as a bare multiplier at a call site, so
 * that every scenario can state what it changed and what it deliberately left
 * alone. A shock without an eligible target set is unusable: the caller gets
 * Cannot verify rather than an unshocked pass that looks like a result.
 */
final readonly class ScenarioShock implements \JsonSerializable
{
    public const TYPE_VOLUME_FACTOR = 'volume_factor';

    public const TYPE_COST_RATE_FACTOR = 'cost_rate_factor';

    public const TYPE_COLLECTION_DELAY_DAYS = 'collection_delay_days';

    /** Categories a cost-rate shock may touch. Tax, financing receipts and
     * historical actuals are never eligible: the specification requires those
     * to be recomputed as responses, not multiplied.
     *
     * @var list<string>
     */
    public const COST_CATEGORIES = ['direct_cost', 'fixed_cost', 'capex'];

    /** @param list<string> $targets Eligible target identifiers (stream ids, cost categories or collection groups).
     * @param  list<string>|null  $periods  Eligible periods, or null for every forecast period.
     */
    public function __construct(
        public string $type,
        public string $factor,
        public array $targets,
        public ?array $periods = null,
        public ?string $basis = null,
    ) {
        if (! in_array($type, [self::TYPE_VOLUME_FACTOR, self::TYPE_COST_RATE_FACTOR, self::TYPE_COLLECTION_DELAY_DAYS], true)) {
            throw new \InvalidArgumentException('Unknown scenario shock type.');
        }
        if ($targets === []) {
            throw new \InvalidArgumentException('A scenario shock must state its eligible targets.');
        }
        if ($type === self::TYPE_COST_RATE_FACTOR) {
            foreach ($targets as $target) {
                if (! in_array($target, self::COST_CATEGORIES, true)) {
                    throw new \InvalidArgumentException('Cost-rate shocks apply only to direct cost, fixed cost or capex categories.');
                }
            }
        }
        if ($type === self::TYPE_COLLECTION_DELAY_DAYS) {
            if (preg_match('/^\d{1,3}$/D', $this->factor) !== 1) {
                throw new \InvalidArgumentException('A collection delay is a whole number of days.');
            }
        } else {
            DriverNumbers::read($this->factor);
        }
        if ($periods === []) {
            throw new \InvalidArgumentException('An empty period list cannot be distinguished from every period; pass null for all.');
        }
    }

    public function factorValue(): BigDecimal
    {
        return BigDecimal::of($this->factor);
    }

    public function delayDays(): int
    {
        if ($this->type !== self::TYPE_COLLECTION_DELAY_DAYS) {
            throw new \LogicException('Only a collection-delay shock carries a day count.');
        }

        return (int) $this->factor;
    }

    public function appliesToPeriod(string $period): bool
    {
        return $this->periods === null || in_array($period, $this->periods, true);
    }

    public function appliesToTarget(string $target): bool
    {
        return in_array($target, $this->targets, true);
    }

    /** @return array{type:string,factor:string,targets:list<string>,periods:list<string>|null,basis:?string} */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type, 'factor' => $this->factor, 'targets' => $this->targets,
            'periods' => $this->periods, 'basis' => $this->basis];
    }
}
