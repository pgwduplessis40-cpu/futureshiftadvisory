<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

/**
 * A named scenario: its shocks, the defaults they came from, and the metric
 * used to rank it against the others.
 *
 * The ranking metric is recorded on the definition rather than chosen when
 * results are compared, so a reader can see which measure decided "worst" and
 * the comparison cannot quietly switch measures to produce a tidier story.
 */
final readonly class ScenarioDefinition implements \JsonSerializable
{
    public const RANK_CASH_DEFICIT = 'cash_deficit';

    public const RANK_RESERVE_SHORTFALL = 'reserve_target_shortfall';

    public const RANK_LOWEST_CASH = 'lowest_cash';

    public const RANK_PROFIT = 'profit';

    /** @param list<ScenarioShock> $shocks */
    public function __construct(
        public string $id,
        public string $name,
        public array $shocks,
        public string $defaultsSource,
        public string $rankingMetric = self::RANK_CASH_DEFICIT,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,48}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('A scenario id is a lower-case identifier.');
        }
        if (trim($name) === '' || trim($defaultsSource) === '') {
            throw new \InvalidArgumentException('A scenario records its display name and the defaults it came from.');
        }
        if (! in_array($rankingMetric, [self::RANK_CASH_DEFICIT, self::RANK_RESERVE_SHORTFALL, self::RANK_LOWEST_CASH, self::RANK_PROFIT], true)) {
            throw new \InvalidArgumentException('Name the metric used to rank this scenario.');
        }
        // The shock list is a declared contract, as with every other Forecast
        // value object; it is not re-checked at runtime here.
    }

    public function isBaseCase(): bool
    {
        return $this->shocks === [];
    }

    /** @return list<ScenarioShock> */
    public function shocksOfType(string $type): array
    {
        return array_values(array_filter($this->shocks, static fn (ScenarioShock $shock): bool => $shock->type === $type));
    }

    /** Periods this definition may touch, or null when it spans the whole forecast.
     * @return list<string>|null
     */
    public function constrainedPeriods(): ?array
    {
        $periods = [];
        foreach ($this->shocks as $shock) {
            if ($shock->periods === null) {
                return null;
            }
            foreach ($shock->periods as $period) {
                $periods[$period] = true;
            }
        }

        return $periods === [] ? null : array_keys($periods);
    }

    /** @return array{id:string,name:string,shocks:list<ScenarioShock>,defaults_source:string,ranking_metric:string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'shocks' => $this->shocks,
            'defaults_source' => $this->defaultsSource, 'ranking_metric' => $this->rankingMetric];
    }
}
