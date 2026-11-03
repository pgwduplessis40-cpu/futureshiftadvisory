<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

/** @phpstan-type Topic array{title:string,guide:string,plan_keys:list<string>} */
final class FounderBriefReconciliationTopics
{
    /** @return array<string,Topic> */
    public static function all(): array
    {
        return [
            'revenue_scope' => ['title' => 'Base revenue and other offers', 'guide' => 'Name the offers included in the base case. Match them to the revenue rows; keep unproven or deliberately excluded offers in separate scenarios. Distinguish historical prices from the current supported price.', 'plan_keys' => ['financial-assumptions', 'revenue-model']],
            'ramp_capacity' => ['title' => 'Monthly ramp, milestones and delivery capacity', 'guide' => 'Record the monthly quantities, start dates and units (days or engagements), including shared founder time across all offers. Reconcile calendar milestones with the forecast period and check deposits and collection timing.', 'plan_keys' => ['financial-assumptions', 'goals-objectives', 'organisation-management']],
            'profitability' => ['title' => 'Break-even and profit targets', 'guide' => 'Explain differences between targets and calculated results. Update the narrative from the agreed forecast; do not force a break-even year or margin into the calculation.', 'plan_keys' => ['financial-assumptions', 'revenue-model']],
            'owner_pay' => ['title' => 'Owner pay and essential overheads', 'guide' => 'Agree pay amounts and effective dates or triggers for each increase. Check labels against rates. Separate owner pay from business overheads when comparing monthly totals.', 'plan_keys' => ['financial-assumptions']],
            'cash_funding' => ['title' => 'Opening cash and funding cover', 'guide' => 'Use one dated opening balance and identify restricted reserves and funding sources without double counting. Distinguish a cash deficit from a planned buffer shortfall and document how any gap will be covered.', 'plan_keys' => ['financial-assumptions', 'launch-funding']],
            'forecast_period' => ['title' => 'Forecast period and later-year commitments', 'guide' => 'Agree the start date and forecast horizon. Extend the saved Budget to cover committed later-year spending, or explicitly resolve which plans are conditional and outside the issued forecast. Separate elapsed actual months from projections.', 'plan_keys' => ['financial-assumptions', 'goals-objectives']],
            'commitments' => ['title' => 'Costs, events, insurance and intellectual property', 'guide' => 'Trace each dated commitment to a cost row, including delegated work, travel, events and each brand registration. Record who pays and distinguish committed from conditional spending. Check insurance scope and overlap; do not duplicate amounts already in direct costs.', 'plan_keys' => ['goals-objectives', 'financial-assumptions', 'legal-environment']],
            'asset_treatment' => ['title' => 'Assets and operating costs', 'guide' => 'Confirm the saved classification, purchase date and useful life of vehicles, equipment and improvements. Check their cash and profit effects in the appropriate forecast years.', 'plan_keys' => ['financial-assumptions']],
            'distributions' => ['title' => 'Giving, distributions and reinvestment', 'guide' => 'Record whether profit allocations are intentions or scheduled cash payments, their timing and retained-cash impact. Do not treat all after-tax allocations as operating costs or count reinvestment twice.', 'plan_keys' => ['financial-assumptions', 'launch-funding']],
            'evidence' => ['title' => 'Demand, pricing and market evidence', 'guide' => 'Reference the attached records and traceable market sources supporting quantities, prices and costs. Distinguish early interviews or pilot sales from a supported sales pipeline. Explain any evidence limitation in the issued narrative.', 'plan_keys' => ['industry-context', 'differentiation', 'revenue-model']],
            'narrative' => ['title' => 'One current financial narrative', 'guide' => 'Remove superseded updates and reconcile the financial sections with the saved Budget. Regenerate the executive summary after source changes, then inspect the complete plan, Budget Pack and founder brief before release.', 'plan_keys' => ['financial-assumptions', 'goals-objectives', 'revenue-model']],
        ];
    }
}
