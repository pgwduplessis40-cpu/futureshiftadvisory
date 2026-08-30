<?php

declare(strict_types=1);

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\FunderReadyBusinessPlanBuilder;
use App\Services\Entrepreneurs\PlanRequirements;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$profile = new EntrepreneurProfile([
    'name' => 'Lender Readiness Sample',
    'company_name' => 'Harbour Planning Limited',
    'concept_summary' => 'A practical advisory studio that helps early-stage owners turn validated demand into clear plans and delivery systems.',
]);
$calculator = app(BudgetCalculator::class);
$launchCosts = $calculator->normaliseRows([
    [
        'label' => 'Initial legal and setup',
        'amount' => 3_000,
        'month' => 1,
        'confidence' => 'known',
        'source_type' => 'supplier_quote',
        'source_reference' => 'Legal setup quote, August 2026',
        'source_confirmed' => true,
    ],
]);
$fixedCosts = $calculator->normaliseRows([
    ['label' => 'Founder compensation', 'amount' => 1_000, 'cadence' => 'weekly', 'cadence_confirmed' => true, 'confidence' => 'estimate', 'source_type' => 'owner_record', 'source_reference' => 'Founder draw schedule, August 2026', 'source_confirmed' => true],
    ['label' => 'Annual registry fee', 'amount' => 1_200, 'cadence' => 'annual', 'cadence_confirmed' => true, 'confidence' => 'known', 'source_type' => 'xero_ledger', 'source_reference' => 'Xero annual registry ledger', 'source_confirmed' => true],
    ['label' => 'Client software', 'amount' => 850, 'cadence' => 'monthly', 'cadence_confirmed' => true, 'confidence' => 'known', 'source_type' => 'supplier_quote', 'source_reference' => 'Current software vendor quote', 'source_confirmed' => true],
]);
$revenue = $calculator->normaliseRows([
    [
        'label' => 'Advisory intensives',
        'amount' => 1_500,
        'quantity' => 2,
        'month' => 1,
        'growth_percent' => 25,
        'growth_cadence' => 'annual',
        'growth_cadence_confirmed' => true,
        'monthly_capacity_units' => 3,
        'capacity_confirmed' => true,
        'founder_capacity_units' => 2,
        'contractor_unit_cost' => 450,
        'contractor_cost_confirmed' => true,
        'unit_label' => 'intensives',
        'gross_profit_percent' => 78,
        'confidence' => 'estimate',
        'source_type' => 'pipeline_evidence',
        'source_reference' => 'Signed pipeline and contractor rate schedule, August 2026',
        'source_confirmed' => true,
    ],
]);
$funding = $calculator->normaliseRows([
    ['label' => 'Founder cash', 'amount' => 80_000, 'month' => 1, 'confidence' => 'known'],
]);
$futureCosts = $calculator->normaliseFutureCosts([
    ['label' => 'Workshop equipment', 'amount' => 6_000, 'year' => 2, 'classification' => 'capital', 'useful_life_years' => 3, 'confidence' => 'estimate'],
]);
$assumptions = [
    'forecast_start_month' => '2026-09',
    'opening_cash_balance' => 5_000,
    'opening_cash_verified' => true,
    'debtor_days' => 30,
    'creditor_days' => 30,
    'working_capital_verified' => true,
    'forecast_start_confirmed' => true,
    'funding_position' => 'self_funded',
    'funding_position_confirmed' => true,
    'funding_request_purpose' => '',
    'revenue_growth_percent' => 12,
    'cost_inflation_percent' => 3,
    'target_gross_profit_percent' => 70,
    'target_net_profit_before_tax_percent' => 15,
    'target_net_profit_after_tax_percent' => 10,
    'year_two_revenue_basis' => 'exit_run_rate',
];
$computed = $calculator->compute(
    launchCosts: $launchCosts,
    monthlyFixedCosts: $fixedCosts,
    revenueForecast: $revenue,
    fundingSources: $funding,
    expectedRunwayMonths: 6,
    forecastYears: 2,
    assumptions: $assumptions,
    futureCosts: $futureCosts,
    companyTaxRatePercent: 28,
);
$budget = new EntrepreneurBudget([
    'status' => EntrepreneurBudget::STATUS_COMPLETE,
    'expected_runway_months' => 6,
    'forecast_years' => 2,
    'assumptions' => $computed['assumptions'],
    'launch_costs' => $launchCosts,
    'monthly_fixed_costs' => $fixedCosts,
    'future_costs' => $futureCosts,
    'revenue_forecast' => $revenue,
    'funding_sources' => $funding,
    'computed' => $computed,
    'flags' => [],
]);
$plan = new BusinessPlan(['title' => 'Harbour Planning Limited business plan']);
$plan->setRelation('budgetRunway', $budget);
$plan->setRelation('sections', new EloquentCollection);

$phases = new EloquentCollection;
foreach (PlanRequirements::definitions() as $phaseKey => $definition) {
    $phase = new PlanPhase(['key' => $phaseKey, 'title' => $definition['title']]);
    $sections = new EloquentCollection;
    foreach ($definition['requirements'] as $requirement) {
        if (($requirement['type'] ?? null) === 'budget') {
            continue;
        }

        $body = match ($requirement['key']) {
            'business-type-location' => 'Harbour Planning Limited is an advisory business based in Hamilton, delivering focused planning intensives online and in person.',
            'industry-context' => 'The founder has attached customer interview evidence and a dated market note to support the demand assumptions in this plan.',
            'risk-register' => "- Key-person risk: document delivery methods and maintain advisor cover.\n- Competitor risk: review named alternatives and conversion monthly.",
            'executive-summary' => 'Harbour Planning Limited provides practical advisory intensives for early-stage owners. The lender decision is whether the stated founder funding and cash buffer are sufficient for the capacity-capped forecast.',
            default => 'This completed section sets out the current decision, responsible owner, evidence, and review date for the business plan.',
        };
        $section = new PlanSection([
            'key' => 'founder-'.$phaseKey.'-'.$requirement['key'],
            'title' => $requirement['title'],
            'body' => $body,
            'completeness_status' => PlanSection::STATUS_COMPLETE,
            'metadata' => ['requirement_key' => $requirement['key']],
            'attached_document_ids' => $requirement['key'] === 'industry-context' ? ['11111111-1111-4111-8111-111111111111'] : [],
        ]);
        $sections->push($section);
    }
    $phase->setRelation('sections', $sections);
    $phases->push($phase);
}
$plan->setRelation('phases', $phases);
$plan->setRelation('sections', $phases->flatMap(fn ($phase) => $phase->sections));

$outputDirectory = __DIR__.'/../../output/pdf';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}

if (! in_array('--funder-ready-only', $argv, true)) {
    file_put_contents($outputDirectory.'/lender-readiness-budget-pack-sample.pdf', app(BudgetPackBuilder::class)->fallbackPdf($profile, $plan));
    file_put_contents($outputDirectory.'/lender-readiness-business-plan-sample.pdf', app(BusinessPlanPreviewRenderer::class)->pdf($profile, $plan));
}

file_put_contents($outputDirectory.'/funder-ready-business-plan-sample.pdf', app(FunderReadyBusinessPlanBuilder::class)->pdf($profile, $plan));

echo "Generated lender-readiness samples.\n";
