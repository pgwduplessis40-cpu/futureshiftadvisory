<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ScenarioRunner;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * The external funding request, and the reasoning that sized it.
 *
 * SS-03 will not read an amount on its own. It asks which requirement the
 * request answers, which scenario that requirement came from, what was
 * applied before reaching for external money, and when the money is needed -
 * because a request sized against the base case and one sized against the
 * worst downside are different asks, and the amount alone cannot say which.
 *
 * The request is stated here rather than left to the budget, and the
 * reconstruction supersedes the budget's record of it. The requirement it
 * answers comes from this model's own scenarios, and an ask stated in one
 * model while sized against another's downside is two models' arithmetic
 * presented as one answer. Where no request is recorded the key goes
 * unstated rather than falling back to the budget's, on the same footing as
 * the funding inventory: a figure taken off the old model is not evidence
 * about the new one.
 */
final class FundingRequestFacts implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    public function capture(SourceRecords $source, ForecastModelInputs $inputs, array $at): array
    {
        $request = $inputs->fundingRequest;
        if ($request === null) {
            return [];
        }
        $where = [$source->reference('forecast_model.drivers.funding_request')];

        // The amount carries the date it is needed by. An undated request
        // cannot be checked against the month its requirement arises in, and
        // the rule reads it as unstated rather than as needed whenever.
        $facts = [new Fact('model.external_request', $request->amount, unit: 'NZD',
            basis: ScenarioRunner::SCOPE, asOf: $request->neededOn, confirmed: true, sources: $where)];

        // Each of these is absent until an advisor states it. None is
        // inferred: an unstated metric must never read as a cash deficit,
        // because the two metrics give different amounts off the same
        // scenarios, and an unstated non-external total must never read as
        // nothing applied first, which would overstate every ask by whatever
        // the business was going to put in itself.
        foreach ([
            'funding.request_metric' => $request->metric,
            'funding.request_basis_scenario' => $request->basisScenario,
            'funding.reserve_only_disclosure' => $request->reserveOnlyDisclosure,
        ] as $key => $value) {
            if ($value !== null) {
                $facts[] = new Fact($key, $value, confirmed: true, sources: $where);
            }
        }
        if ($request->nonExternalTotal !== null) {
            $facts[] = new Fact('funding.non_external_total', $request->nonExternalTotal, unit: 'NZD',
                basis: 'applied_before_the_external_request', confirmed: true, sources: $where);
        }

        return $facts;
    }
}
