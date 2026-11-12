<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * The tax arrangement a reconstruction carries: what is taxed, at what rate,
 * against what losses, and when the cash leaves.
 *
 * SS-01 holds each scenario's four against the base case's, because a shock is
 * a change to trading rather than to the tax the business is subject to. The
 * comparison only means something if both sides are derived the same way from
 * the model they belong to, so one emitter serves the reconstruction and every
 * scenario. Deriving the scenario's from the base inputs would turn the check
 * into an assertion that it passes.
 */
final class TaxPolicyFacts implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /**
     * None at all when no policy is recorded.
     *
     * The four are one arrangement, and reporting a payment schedule for a tax
     * nobody has established the basis of would state the timing of a figure
     * that has none.
     *
     * @return list<Fact>
     */
    public function capture(SourceRecords $source, ForecastModelInputs $inputs, string $scenario = 'base'): array
    {
        $policy = $inputs->taxPolicy;
        if ($policy === null) {
            return [];
        }
        $at = [$source->reference('forecast_model.drivers.tax_policy')];
        // Both source records, because the rule requires a confirmed policy
        // and a policy nobody can point at is an assertion rather than a
        // record. Either one absent leaves it stated but unconfirmed.
        $confirmed = trim($policy->policySource ?? '') !== '' && trim($policy->eligibilitySource ?? '') !== '';
        $plan = $inputs->taxCashPlan;
        // When the cash leaves is a separate answer from what the expense is,
        // and the plan states its own scope: a business with nothing due and
        // one nobody has asked about are different, and the state says which.
        // Confirmed only where the record behind it is named, on the same
        // footing as the policy above.
        $paymentConfirmed = $plan !== null && trim($plan->taxSource ?? '') !== '';

        return [
            new Fact('tax.policy', $policy->lossesEligible === null ? null
                : ($policy->lossesEligible ? 'loss_carry_forward' : 'losses_not_eligible'),
                scenario: $scenario, basis: $policy->entityBasis, confirmed: $confirmed, sources: $at),
            new Fact('tax.rate', $policy->ratePercent, scenario: $scenario, unit: 'percent',
                confirmed: $confirmed, sources: $at),
            new Fact('tax.opening_losses', $inputs->openingEligibleLosses, scenario: $scenario, unit: 'NZD',
                confirmed: $confirmed, sources: [$source->reference('forecast_model.drivers.opening_eligible_losses')]),
            new Fact('tax.payment_policy', $plan?->taxState, scenario: $scenario,
                basis: $plan?->taxSource, confirmed: $paymentConfirmed,
                sources: [$source->reference('forecast_model.drivers.tax_cash_plan')]),
        ];
    }
}
