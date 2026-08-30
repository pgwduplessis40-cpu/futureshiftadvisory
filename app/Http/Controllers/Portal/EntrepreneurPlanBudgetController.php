<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EntrepreneurPlanBudgetController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanRequirements $requirements,
        private readonly EntrepreneurBudgetService $budgets,
    ) {}

    public function budget(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        if (! $this->requirements->budgetUnlocked($plan)) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-budget-locked')
                ->with('entrepreneur_plan_error', 'Complete Foundation: Business type, location, and operating model, plus Financial: Financial assumptions before setting up the budget.');
        }

        $validated = $request->validate([
            '_autosave' => ['sometimes', 'boolean'],
            'expected_runway_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'forecast_years' => ['nullable', 'integer', Rule::in([1, 2, 3, 5])],
            'assumptions' => ['array'],
            'assumptions.opening_cash_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'assumptions.forecast_start_month' => ['nullable', 'date_format:Y-m'],
            'assumptions.revenue_growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'assumptions.year_two_revenue_basis' => ['nullable', 'string', Rule::in(['exit_run_rate', 'year_one_average'])],
            'assumptions.cost_inflation_percent' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'assumptions.target_gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_before_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_after_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'launch_costs' => ['array', 'max:50'],
            'launch_costs.*.label' => ['nullable', 'string', 'max:180'],
            'launch_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'launch_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'launch_costs.*.month' => ['nullable', 'integer', 'min:1', 'max:60'],
            'launch_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'monthly_fixed_costs' => ['array', 'max:50'],
            'monthly_fixed_costs.*.label' => ['nullable', 'string', 'max:180'],
            'monthly_fixed_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'monthly_fixed_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'monthly_fixed_costs.*.cadence' => ['nullable', 'string', Rule::in(['weekly', 'fortnightly', 'monthly', 'quarterly', 'annual'])],
            'monthly_fixed_costs.*.cadence_confirmed' => ['nullable', 'boolean'],
            'monthly_fixed_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'revenue_forecast' => ['array', 'max:50'],
            'revenue_forecast.*.label' => ['nullable', 'string', 'max:180'],
            'revenue_forecast.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'revenue_forecast.*.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'revenue_forecast.*.growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'revenue_forecast.*.monthly_growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'revenue_forecast.*.growth_cadence' => ['nullable', 'string', Rule::in(['monthly', 'annual'])],
            'revenue_forecast.*.growth_cadence_confirmed' => ['nullable', 'boolean'],
            'revenue_forecast.*.monthly_capacity_units' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'revenue_forecast.*.capacity_confirmed' => ['nullable', 'boolean'],
            'revenue_forecast.*.unit_label' => ['nullable', 'string', 'max:60'],
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
            'future_costs.*.classification' => ['nullable', 'string', Rule::in(['operating', 'capital'])],
            'future_costs.*.useful_life_years' => ['nullable', 'integer', 'min:1', 'max:20'],
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

        $this->budgets->update($plan, $validated, $user);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $request->boolean('_autosave')
                    ? 'entrepreneur-budget-autosaved'
                    : 'entrepreneur-budget-saved',
            ]);
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-saved');
    }

    public function acknowledgeBudgetFlag(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80'],
        ]);
        $budget = $plan->budgetRunway()->first();
        abort_unless($budget instanceof EntrepreneurBudget, 404);

        $this->budgets->acknowledgeFlag($budget, (string) $validated['key'], $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-flag-acknowledged');
    }

    public function dismissBudgetAdvisorNudge(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $budget = $plan->budgetRunway()->first();
        abort_unless($budget instanceof EntrepreneurBudget, 404);

        $this->budgets->dismissAdvisorLineNudge($budget, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-advisor-nudge-dismissed');
    }
}
