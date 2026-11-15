<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioDefinition;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * Every driver value a shock can reach, stated per scenario.
 *
 * SS-01 compares each scenario's inputs against the base case's one by one:
 * a value with no declared shock has to be unchanged, and a value with one
 * has to be the baseline with that shock applied. That is the check that
 * catches a scenario which quietly moved something it never declared.
 *
 * The inventory is exactly what `ScenarioApplication` can touch - the three
 * volume fields and the provider's unit cost on each stream month, each fixed
 * cost's rate, and each asset's cost - because the rule is told the inventory
 * is complete and that has to be true. Enumerating a convenient subset while
 * claiming completeness would let an unshocked change hide in whatever was
 * left out.
 *
 * The declared multiplier comes from the definition, never from dividing the
 * scenario value by the base one. Derived that way it would agree with itself
 * however the shock had actually been applied, and the rule would confirm its
 * own arithmetic rather than the engine's.
 */
final class ScenarioInputFacts implements ProvidesMethodology
{
    /** The stream-month fields a volume shock scales, and the unit each is in. */
    private const VOLUME_FIELDS = [
        'booked_units' => 'bookedUnits',
        'delivery_units' => 'deliveryUnits',
        'invoiced_units' => 'invoicedUnits',
    ];

    public function __construct(private readonly ScenarioApplication $application = new ScenarioApplication) {}

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  ForecastModelInputs  $inputs  The reconstruction this scenario ran on, shocked where it was.
     * @param  ScenarioDefinition|null  $definition  None for the base case, which declares no shock.
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    public function capture(ForecastModelInputs $inputs, array $at, string $scenario = 'base',
        ?ScenarioDefinition $definition = null): array
    {
        $offsets = array_flip($inputs->calendar->periods);
        $facts = [];

        foreach ($inputs->streams as $stream) {
            foreach ($stream->schedule as $period => $month) {
                $period = (string) $period;
                if (! isset($offsets[$period])) {
                    continue;
                }
                $label = 'month:'.($offsets[$period] + 1);
                $volume = $definition === null ? null : $this->application->volumeFactor($definition, $stream->id, $period);
                foreach (self::VOLUME_FIELDS as $name => $property) {
                    $facts[] = new Fact('scenario.input', $month->{$property}, 'stream:'.$stream->id.'.'.$name,
                        $label, $scenario, unit: 'units', confirmed: true, sources: $at);
                    if ($volume !== null) {
                        $facts[] = new Fact('scenario.shock_multiplier', (string) $volume,
                            'stream:'.$stream->id.'.'.$name, $label, $scenario, confirmed: true, sources: $at);
                    }
                }
                $direct = $definition === null ? null : $this->application->costFactor($definition, 'direct_cost', $period);
                $facts[] = new Fact('scenario.input', $month->providerUnitCost,
                    'stream:'.$stream->id.'.provider_unit_cost', $label, $scenario, unit: 'NZD',
                    confirmed: true, sources: $at);
                if ($direct !== null) {
                    $facts[] = new Fact('scenario.shock_multiplier', (string) $direct,
                        'stream:'.$stream->id.'.provider_unit_cost', $label, $scenario, confirmed: true, sources: $at);
                }
            }
        }

        // A rate and an asset's cost carry no month of their own, so they are
        // stated once against the current period rather than repeated across
        // the horizon as though each month held its own.
        $fixed = $definition === null ? null : $this->application->costFactor($definition, 'fixed_cost');
        foreach ($inputs->fixedCosts as $cost) {
            $facts[] = new Fact('scenario.input', $cost->rate, 'fixed_cost:'.$cost->id.'.rate',
                scenario: $scenario, unit: 'NZD', confirmed: true, sources: $at);
            if ($fixed !== null) {
                $facts[] = new Fact('scenario.shock_multiplier', (string) $fixed, 'fixed_cost:'.$cost->id.'.rate',
                    scenario: $scenario, confirmed: true, sources: $at);
            }
        }

        $capex = $definition === null ? null : $this->application->costFactor($definition, 'capex');
        foreach ($inputs->assets as $asset) {
            $facts[] = new Fact('scenario.input', $asset->cost, 'asset:'.$asset->id.'.cost',
                scenario: $scenario, unit: 'NZD', confirmed: true, sources: $at);
            if ($capex !== null) {
                $facts[] = new Fact('scenario.shock_multiplier', (string) $capex, 'asset:'.$asset->id.'.cost',
                    scenario: $scenario, confirmed: true, sources: $at);
            }
        }

        // Stated last and only once the enumeration above has run, because it
        // is a claim about that enumeration: every value a shock could have
        // reached is in it.
        $facts[] = new Fact('scenario.input_inventory', 'complete', scenario: $scenario,
            basis: 'every_driver_value_a_shock_can_reach', confirmed: true, sources: $at);

        return $facts;
    }
}
