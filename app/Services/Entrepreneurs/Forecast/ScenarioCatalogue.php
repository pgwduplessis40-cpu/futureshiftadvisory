<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;

/**
 * Builds the six required scenarios from configurable defaults.
 *
 * Every scenario names the defaults it came from so a reader can tell a
 * configured factor from a hard-coded one. Slow start is bounded to the first
 * months of the forecast window supplied by the actuals rebase, which already
 * excludes elapsed reviewed months, so historical actuals stay fixed by
 * construction rather than by a later guard.
 */
final class ScenarioCatalogue implements ProvidesMethodology
{
    public const VERSION = 'phase-2d.1';

    public const BASE = 'base';

    public const REVENUE_DOWNSIDE = 'revenue_downside';

    public const SLOW_START = 'slow_start';

    public const COST_UPSIDE = 'cost_upside';

    public const COLLECTIONS_DELAY = 'collections_delay';

    public const COMBINED_DOWNSIDE = 'combined_downside';

    /** @var array{revenue_volume_factor:string,slow_start_factor:string,slow_start_months:int,cost_rate_factor:string,collection_delay_days:string,cost_categories:list<string>} */
    public const DEFAULTS = [
        'revenue_volume_factor' => '0.8',
        'slow_start_factor' => '0.5',
        'slow_start_months' => 3,
        'cost_rate_factor' => '1.1',
        'collection_delay_days' => '30',
        'cost_categories' => ScenarioShock::COST_CATEGORIES,
    ];

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  list<string>  $streamIds  Streams eligible for a volume shock.
     * @param  list<string>  $collectionTargets  Collection groups eligible for a timing shock.
     * @param  array<string,mixed>  $overrides  Configured replacements for DEFAULTS.
     * @return list<ScenarioDefinition>
     */
    public static function required(ActualsWindow $actuals, array $streamIds, array $collectionTargets, array $overrides = []): array
    {
        if ($streamIds === []) {
            throw new \InvalidArgumentException('Scenario volume shocks need at least one eligible stream.');
        }
        if ($collectionTargets === []) {
            throw new \InvalidArgumentException('Collection-delay shocks need at least one eligible collection target.');
        }

        $defaults = self::resolveDefaults($overrides);
        $source = $overrides === [] ? 'ScenarioCatalogue::DEFAULTS' : 'configured override of ScenarioCatalogue::DEFAULTS';
        $slowStartPeriods = array_slice($actuals->forecastPeriods, 0, $defaults['slow_start_months']);

        if ($slowStartPeriods === []) {
            throw new \InvalidArgumentException('Slow start needs at least one forecast period after the actuals cutoff.');
        }

        $revenueShock = new ScenarioShock(
            ScenarioShock::TYPE_VOLUME_FACTOR,
            $defaults['revenue_volume_factor'],
            $streamIds,
            null,
            'future delivery volumes',
        );
        $costShock = new ScenarioShock(
            ScenarioShock::TYPE_COST_RATE_FACTOR,
            $defaults['cost_rate_factor'],
            $defaults['cost_categories'],
            null,
            'eligible forecast operating and capex cost rates',
        );
        $collectionShock = new ScenarioShock(
            ScenarioShock::TYPE_COLLECTION_DELAY_DAYS,
            $defaults['collection_delay_days'],
            $collectionTargets,
            null,
            'eligible future collection dates',
        );

        return [
            new ScenarioDefinition(self::BASE, 'Base', [], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
            new ScenarioDefinition(self::REVENUE_DOWNSIDE, 'Revenue downside', [$revenueShock], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
            new ScenarioDefinition(self::SLOW_START, 'Slow start', [
                new ScenarioShock(
                    ScenarioShock::TYPE_VOLUME_FACTOR,
                    $defaults['slow_start_factor'],
                    $streamIds,
                    $slowStartPeriods,
                    'first '.count($slowStartPeriods).' forecast months after the actuals cutoff',
                ),
            ], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
            new ScenarioDefinition(self::COST_UPSIDE, 'Cost upside', [$costShock], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
            new ScenarioDefinition(self::COLLECTIONS_DELAY, 'Collections delay', [$collectionShock], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
            new ScenarioDefinition(self::COMBINED_DOWNSIDE, 'Combined downside', [$revenueShock, $costShock, $collectionShock], $source, ScenarioDefinition::RANK_CASH_DEFICIT),
        ];
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{revenue_volume_factor:string,slow_start_factor:string,slow_start_months:int,cost_rate_factor:string,collection_delay_days:string,cost_categories:list<string>}
     */
    private static function resolveDefaults(array $overrides): array
    {
        $defaults = self::DEFAULTS;
        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                throw new \InvalidArgumentException('Unknown scenario default: '.$key);
            }
            $defaults[$key] = $value;
        }
        if (! is_int($defaults['slow_start_months']) || $defaults['slow_start_months'] < 1) {
            throw new \InvalidArgumentException('Slow start covers a whole number of months, at least one.');
        }
        if ($defaults['cost_categories'] === []) {
            throw new \InvalidArgumentException('Cost upside must state the categories it includes.');
        }

        return $defaults;
    }
}
