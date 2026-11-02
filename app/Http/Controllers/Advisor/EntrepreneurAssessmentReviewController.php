<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Jobs\RunEntrepreneurPlanAssessment;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\PlanBudgetFollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EntrepreneurAssessmentReviewController extends Controller
{
    public function review(Request $request, EntrepreneurProfile $entrepreneurProfile, PlanAssessment $planAssessment, PlanBudgetFollowUp $followUp, AuditWriter $audit): RedirectResponse
    {
        Gate::authorize('finaliseAssessment', $entrepreneurProfile);
        if (is_string($request->input('note'))) {
            $request->merge(['note' => trim($request->input('note'))]);
        }
        $validated = $request->validate(['note' => ['required', 'string', 'min:15', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DB::transaction(function () use ($entrepreneurProfile, $planAssessment, $followUp, $audit, $actor, $validated): void {
            $plan = $this->currentPlan($entrepreneurProfile, $planAssessment);
            $assessment = PlanAssessment::query()->lockForUpdate()->findOrFail($planAssessment->getKey());
            $coherence = $followUp->forAssessment($assessment);
            if (($coherence['advisor_reviews'] ?? []) === []) {
                throw ValidationException::withMessages(['note' => 'There are no advisor decisions outstanding for this assessment.']);
            }
            $inputsHash = $followUp->currentInputsHash($plan);
            if (! hash_equals($followUp->snapshotInputsHash((array) $assessment->plan_snapshot), $inputsHash)) {
                throw ValidationException::withMessages(['note' => 'The plan or Budget changed after this assessment. Reassess it before recording a decision.']);
            }
            $scope = (array) $assessment->scoring_scope;
            $scope['plan_budget_review'] = [
                'note' => trim($validated['note']), 'reviewed_at' => now()->toIso8601String(),
                'reviewed_by_user_id' => (int) $actor->getKey(),
                'findings_hash' => $followUp->findingsHash($coherence['advisor_reviews']),
                'inputs_hash' => $inputsHash,
            ];
            $assessment->forceFill(['scoring_scope' => $scope])->save();
            $audit->record('entrepreneur.plan_budget_review_recorded', subject: $assessment, actor: $actor, after: $scope['plan_budget_review']);
        });

        return back()->with('status', 'entrepreneur-plan-budget-reviewed');
    }

    public function recalculate(Request $request, EntrepreneurProfile $entrepreneurProfile, PlanAssessment $planAssessment, PlanBudgetFollowUp $followUp, EntrepreneurBudgetService $budgets, Assessment $assessments): RedirectResponse
    {
        Gate::authorize('finaliseAssessment', $entrepreneurProfile);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $plan = DB::transaction(function () use ($entrepreneurProfile, $planAssessment, $followUp, $budgets, $assessments, $actor): BusinessPlan {
            $plan = $this->currentPlan($entrepreneurProfile, $planAssessment);
            $coherence = $followUp->forAssessment($planAssessment);
            if (($coherence['calculation_issues'] ?? []) === []) {
                throw ValidationException::withMessages(['assessment' => 'No calculation issue requires a refresh.']);
            }
            $budget = $plan->budgetRunway()->lockForUpdate()->first();
            abort_unless($budget instanceof EntrepreneurBudget, 422, 'Complete the Budget before recalculating.');
            $budgets->update($plan, $budget->only([
                'revision', 'expected_runway_months', 'forecast_years', 'assumptions', 'launch_costs',
                'monthly_fixed_costs', 'future_costs', 'revenue_forecast', 'funding_sources', 'funding_scenarios',
            ]), $actor);
            $assessments->queueFirstPass($plan->refresh(), $actor);

            return $plan;
        });
        try {
            RunEntrepreneurPlanAssessment::dispatch((string) $plan->getKey(), (int) $actor->getKey());
        } catch (Throwable $exception) {
            $assessments->markQueuedFirstPassFailed($plan, $actor, $exception);
            report($exception);

            return back()->withErrors(['assessment' => 'The Budget was recalculated, but reassessment could not start. Run reassessment again.']);
        }

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-budget-recalculated');
    }

    private function currentPlan(EntrepreneurProfile $profile, PlanAssessment $assessment): BusinessPlan
    {
        $plan = BusinessPlan::query()->lockForUpdate()->findOrFail($assessment->business_plan_id);
        abort_unless((string) $plan->entrepreneur_profile_id === (string) $profile->getKey(), 404);
        $latest = $plan->assessments()->orderByDesc('round')->first();
        if ($latest?->getKey() !== $assessment->getKey() || $latest->finalised_at !== null
            || in_array($plan->assessment_run_status, ['queued', 'running'], true)) {
            throw ValidationException::withMessages(['assessment' => 'Open the latest unfinished assessment after any running assessment completes.']);
        }

        return $plan;
    }
}
