<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Jobs\RunEntrepreneurPlanAssessment;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\FixedCostCadenceQuantityGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EntrepreneurBudgetCorrectionController extends Controller
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function repairDuplicateCadenceQuantity(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        BusinessPlan $businessPlan,
        EntrepreneurBudgetService $budgets,
        Assessment $assessments,
    ): RedirectResponse {
        Gate::authorize('assess', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);

        $validated = $request->validate([
            'row_index' => ['required', 'integer', 'min:0'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $budget = $businessPlan->budgetRunway()->first();
        abort_unless($budget instanceof EntrepreneurBudget, 404, 'No budget has been created for this plan.');

        $rows = array_values((array) $budget->monthly_fixed_costs);
        $rowIndex = (int) $validated['row_index'];
        $conflict = collect((new FixedCostCadenceQuantityGuard)->conflicts($rows))
            ->first(fn (array $candidate): bool => $candidate['index'] === $rowIndex);

        if (! is_array($conflict) || ! isset($rows[$rowIndex])) {
            throw ValidationException::withMessages([
                'row_index' => 'This fixed-cost row is no longer a duplicate cadence count. Refresh the budget audit before making a correction.',
            ]);
        }

        $beforeRow = $rows[$rowIndex];
        $beforeComputed = (array) $budget->computed;
        $rows[$rowIndex]['quantity'] = 1;

        $corrected = $budgets->update($businessPlan, $this->budgetInput($budget, $rows), $actor);
        $afterRow = (array) ($corrected->monthly_fixed_costs[$rowIndex] ?? []);
        $afterComputed = (array) $corrected->computed;

        $this->audit->record('entrepreneur.budget_fixed_cost_cadence_repaired', subject: $corrected, actor: $actor, after: [
            'business_plan_id' => $businessPlan->getKey(),
            'fixed_cost_row_index' => $rowIndex,
            'fixed_cost_label' => $conflict['label'],
            'correction' => 'Reset a duplicated '.$conflict['cadence'].' billing-period count to one parallel unit.',
            'before' => [
                'fixed_cost' => $beforeRow,
                'monthly_fixed_costs' => data_get($beforeComputed, 'monthly_fixed_costs'),
                'required_additional_funding' => data_get($beforeComputed, 'required_additional_funding'),
            ],
            'after' => [
                'fixed_cost' => $afterRow,
                'monthly_fixed_costs' => data_get($afterComputed, 'monthly_fixed_costs'),
                'required_additional_funding' => data_get($afterComputed, 'required_additional_funding'),
            ],
        ]);

        try {
            if ($assessments->queueFirstPass($businessPlan->refresh(), $actor)) {
                RunEntrepreneurPlanAssessment::dispatch((string) $businessPlan->getKey(), (int) $actor->getKey());

                return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
                    ->with('status', 'entrepreneur-budget-fixed-cost-cadence-repaired-assessment-queued');
            }
        } catch (Throwable $exception) {
            $assessments->markQueuedFirstPassFailed($businessPlan, $actor, $exception);
            report($exception);

            return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
                ->with('status', 'entrepreneur-budget-fixed-cost-cadence-repaired')
                ->withErrors(['assessment' => 'The budget was corrected, but the reassessment could not be queued. Retry the assessment from this workspace.']);
        }

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', 'entrepreneur-budget-fixed-cost-cadence-repaired-assessment-running');
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyFixedCosts
     * @return array<string, mixed>
     */
    private function budgetInput(EntrepreneurBudget $budget, array $monthlyFixedCosts): array
    {
        return [
            'revision' => (int) $budget->revision,
            'expected_runway_months' => $budget->expected_runway_months,
            'forecast_years' => $budget->forecast_years,
            'assumptions' => (array) $budget->assumptions,
            'launch_costs' => (array) $budget->launch_costs,
            'monthly_fixed_costs' => $monthlyFixedCosts,
            'future_costs' => (array) $budget->future_costs,
            'revenue_forecast' => (array) $budget->revenue_forecast,
            'funding_sources' => (array) $budget->funding_sources,
            'funding_scenarios' => (array) $budget->funding_scenarios,
        ];
    }

    private function assertPlanBelongsToProfile(BusinessPlan $plan, EntrepreneurProfile $profile): void
    {
        abort_unless((string) $plan->entrepreneur_profile_id === (string) $profile->getKey(), 404);
    }
}
