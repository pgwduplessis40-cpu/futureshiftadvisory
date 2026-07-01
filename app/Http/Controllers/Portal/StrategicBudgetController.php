<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class StrategicBudgetController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly StrategicBudgetService $budgets,
    ) {}

    public function show(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));

        return Inertia::render('portal/StrategicPlanBudget', [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
                'engagement_type' => $client->engagement_type instanceof EngagementType
                    ? $client->engagement_type->value
                    : (string) $client->engagement_type,
                'engagement_type_label' => $client->engagement_type instanceof EngagementType
                    ? $client->engagement_type->label()
                    : str((string) $client->engagement_type)->replace('_', ' ')->title()->toString(),
            ],
            'budget' => $this->budgets->portalPayload($budget),
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'onboardingUrl' => route('portal.onboarding.step', ['step' => 'documents'], absolute: false),
            'dashboardUrl' => route('portal.dashboard', absolute: false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));

        $this->budgets->update($budget, $this->validatedBudget($request), $user);

        return to_route('portal.business-plan-budget.show')->with('status', 'business-plan-budget-saved');
    }

    public function submit(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));
        if (! $budget->isUnlocked()) {
            return to_route('portal.business-plan-budget.show')
                ->with('status', 'business-plan-budget-locked')
                ->with('business_plan_budget_error', 'Upload a P&L or management accounts file before submitting the combined plan and budget.');
        }
        if (($this->budgets->portalPayload($budget)['business_plan_ready'] ?? false) !== true) {
            return to_route('portal.business-plan-budget.show')
                ->with('status', 'business-plan-incomplete')
                ->with('business_plan_budget_error', 'Complete every plan section before submitting for advisor approval.');
        }

        $this->budgets->submit($budget, $user);

        return to_route('portal.business-plan-budget.show')->with('status', 'business-plan-budget-submitted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBudget(Request $request): array
    {
        return $request->validate([
            'business_plan_sections' => ['array', 'max:12'],
            'business_plan_sections.*.key' => ['required_with:business_plan_sections', 'string', 'max:80'],
            'business_plan_sections.*.answer' => ['nullable', 'string', 'max:6000'],
            'horizon_months' => ['required', 'integer', Rule::in([12, 24, 36])],
            'expected_runway_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'assumptions' => ['array'],
            'assumptions.revenue_growth_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'assumptions.cost_inflation_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_before_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_after_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'implementation_costs' => ['array', 'max:50'],
            'implementation_costs.*.label' => ['nullable', 'string', 'max:180'],
            'implementation_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'implementation_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'implementation_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'monthly_fixed_costs' => ['array', 'max:50'],
            'monthly_fixed_costs.*.label' => ['nullable', 'string', 'max:180'],
            'monthly_fixed_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'monthly_fixed_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'monthly_fixed_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'revenue_forecast' => ['array', 'max:50'],
            'revenue_forecast.*.label' => ['nullable', 'string', 'max:180'],
            'revenue_forecast.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'revenue_forecast.*.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'revenue_forecast.*.monthly_growth_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'revenue_forecast.*.variable_cost_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_sources' => ['array', 'max:50'],
            'funding_sources.*.label' => ['nullable', 'string', 'max:180'],
            'funding_sources.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_sources.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'funding_sources.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'future_costs' => ['array', 'max:50'],
            'future_costs.*.label' => ['nullable', 'string', 'max:180'],
            'future_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'future_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'future_costs.*.year' => ['nullable', 'integer', 'min:2', 'max:5'],
            'future_costs.*.recurring' => ['nullable', 'boolean'],
            'future_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_scenarios' => ['array', 'max:10'],
            'funding_scenarios.*.name' => ['nullable', 'string', 'max:180'],
            'funding_scenarios.*.type' => ['nullable', 'string', Rule::in(['bank_loan', 'investor', 'mixed'])],
            'funding_scenarios.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_scenarios.*.year' => ['nullable', 'integer', 'min:1', 'max:5'],
            'funding_scenarios.*.interest_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.term_years' => ['nullable', 'integer', 'min:0', 'max:30'],
            'funding_scenarios.*.interest_only_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'funding_scenarios.*.investor_equity_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
        ]);
    }

    private function latestDueDiligencePlan(Client $client): ?BusinessPlan
    {
        $engagement = DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();

        if (! $engagement instanceof DdEngagement) {
            return null;
        }

        return BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->latest()
            ->first();
    }
}
