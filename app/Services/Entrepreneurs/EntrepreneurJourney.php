<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Portal\EntrepreneurPlanRequirements;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\FoundingAdvisoryEngagement;
use App\Models\IdeaValidation;
use App\Models\PlanAssessment;
use App\Models\Proposal;

/**
 * Produces the single, factual view of an entrepreneur's journey used by the
 * portal shell, dashboard and workspace.  Presentation must not infer the
 * current service from a user type or a stale local progress value.
 *
 * @phpstan-type JourneyPayload array{
 *     state:string,
 *     active_service:string,
 *     includes_idea_validation:bool,
 *     includes_plan_budget:bool,
 *     idea_validation:array{submitted:bool,approved:bool},
 *     plan:array{exists:bool,completion:array{total:int,completed:int,percent:int}},
 *     assessment:array{exists:bool,finalised:bool,status_label:string},
 *     advisory:array{available:bool,label:string,url:string},
 *     next:array{label:string,description:string,url:string},
 *     tabs:array{advisory:bool,idea_validation:bool,plan_budget:bool}
 * }
 */
final class EntrepreneurJourney
{
    public function __construct(
        private readonly EntrepreneurPlanRequirements $requirements,
        private readonly AdvisoryReadiness $advisoryReadiness,
    ) {}

    /**
     * @param  array{includes_idea_validation:bool,includes_plan_budget:bool,package_label:string,source_activation_id:string|null}  $packageAccess
     * @return JourneyPayload
     */
    public function payload(
        EntrepreneurProfile $profile,
        array $packageAccess,
        ?BusinessPlan $plan = null,
    ): array {
        $plan ??= BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with('phases.sections', 'budgetRunway', 'assessments')
            ->latest('updated_at')
            ->latest()
            ->first();
        $plan?->loadMissing('phases.sections', 'budgetRunway', 'assessments');
        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->first();
        $ideaApproved = $validation instanceof IdeaValidation
            && $validation->advisor_gate_passed_at !== null;
        $includesPlanBudget = (bool) $packageAccess['includes_plan_budget'];
        $includesIdeaValidation = (bool) $packageAccess['includes_idea_validation'];
        $completion = $plan instanceof BusinessPlan
            ? $this->completion($plan)
            : ['total' => 0, 'completed' => 0, 'percent' => 0];
        $assessment = $plan instanceof BusinessPlan
            ? $plan->assessments->sortByDesc('round')->first()
            : null;
        $finalisedAssessment = $assessment instanceof PlanAssessment
            && $assessment->finalised_at !== null;
        $advisoryAvailable = $this->advisoryReadiness->currentSignalForPlan($plan) !== null;
        $foundingAdvisory = FoundingAdvisoryEngagement::query()
            ->with('proposal')
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('updated_at')
            ->first();
        $proposal = $foundingAdvisory?->proposal;
        $proposalReleased = $proposal instanceof Proposal
            && $proposal->status === ProposalStatus::Released;

        if ($foundingAdvisory instanceof FoundingAdvisoryEngagement) {
            if ($foundingAdvisory->status === FoundingAdvisoryEngagement::STATUS_PROPOSAL_SENT && $proposalReleased) {
                $state = 'advisory_proposal';
                $next = [
                    'label' => 'Review advisory proposal',
                    'description' => 'Your Business Plan & Budget is complete and your advisor has prepared the proposal for the implementation work. Review and accept it to begin the jointly managed 90-day roadmap.',
                    'url' => route('portal.proposals.signoff.show', $proposal, absolute: false),
                ];
            } elseif (in_array($foundingAdvisory->status, [
                FoundingAdvisoryEngagement::STATUS_ADVISORY_READY,
                FoundingAdvisoryEngagement::STATUS_PROPOSAL_DRAFT,
            ], true)) {
                $state = 'advisory_preparing';
                $next = [
                    'label' => 'Await advisory proposal',
                    'description' => 'Your advisor is preparing the implementation proposal from the completed Business Plan & Budget and its assessment.',
                    'url' => route('portal.entrepreneur.dashboard', ['journey' => 'advisory'], absolute: false),
                ];
            } else {
                $state = 'advisory_implementation';
                $next = [
                    'label' => 'Review implementation roadmap',
                    'description' => 'Your advisor is working with you on the committed 90-day plan and the provisional 180- and 270-day horizons.',
                    'url' => route('portal.entrepreneur.dashboard', ['journey' => 'advisory'], absolute: false),
                ];
            }
        } elseif ($includesPlanBudget && ! $plan instanceof BusinessPlan) {
            $state = 'plan_budget_start';
            $next = [
                'label' => 'Start Business Plan & Budget',
                'description' => 'Turn your validated idea into a practical business plan and financial forecast.',
                'url' => route('portal.entrepreneur.plan.show', ['journey' => 'plan-budget'], absolute: false),
            ];
        } elseif ($includesPlanBudget) {
            $underAdvisorReview = $plan instanceof BusinessPlan
                && in_array($plan->status, [BusinessPlan::STATUS_SUBMITTED, BusinessPlan::STATUS_ASSESSING], true);
            $state = $finalisedAssessment
                ? 'plan_budget_assessed'
                : ($underAdvisorReview ? 'plan_budget_review' : 'plan_budget_building');
            $next = [
                'label' => $completion['percent'] === 100
                    ? ($underAdvisorReview
                        ? 'Business Plan & Budget is with your advisor'
                        : 'Review Business Plan & Budget')
                    : 'Continue Business Plan & Budget',
                'description' => $underAdvisorReview
                    ? 'Your submitted plan is with your advisor. You will be able to review the assessment and agreed next service when feedback is ready.'
                    : ($completion['percent'] === 100
                        ? 'Your plan is complete. Review the assessment and agreed next service with your advisor.'
                        : 'Continue the next incomplete plan requirement. Changes save automatically as you work.'),
                'url' => route('portal.entrepreneur.plan.show', ['journey' => 'plan-budget'], absolute: false),
            ];
        } elseif ($ideaApproved) {
            $state = 'idea_validation_approved';
            $next = [
                'label' => 'Continue to Business Plan & Budget',
                'description' => 'Your idea is validated. Purchase or open Business Plan & Budget to develop the operating plan and forecast.',
                'url' => route('portal.entrepreneur.plan-budget.show', absolute: false),
            ];
        } elseif ($validation instanceof IdeaValidation) {
            $state = 'idea_validation_review';
            $next = [
                'label' => 'Review Idea Validation',
                'description' => 'Review the current validation and respond to any advisor feedback.',
                'url' => route('portal.entrepreneur.plan.show', ['journey' => 'idea-validation'], absolute: false),
            ];
        } else {
            $state = 'idea_validation_start';
            $next = [
                'label' => 'Start Idea Validation',
                'description' => 'Describe the problem, customer, solution, evidence of demand, and revenue model.',
                'url' => route('portal.entrepreneur.plan.show', ['journey' => 'idea-validation'], absolute: false),
            ];
        }

        $activeService = $foundingAdvisory instanceof FoundingAdvisoryEngagement
            ? 'Advisory'
            : ($includesPlanBudget ? 'Business Plan & Budget' : 'Idea Validation');

        return [
            'state' => $state,
            'active_service' => $activeService,
            'includes_idea_validation' => $includesIdeaValidation,
            'includes_plan_budget' => $includesPlanBudget,
            'idea_validation' => [
                'submitted' => $validation instanceof IdeaValidation && $validation->recalled_at === null,
                'approved' => $ideaApproved,
            ],
            'plan' => [
                'exists' => $plan instanceof BusinessPlan,
                'completion' => $completion,
            ],
            'assessment' => [
                'exists' => $assessment instanceof PlanAssessment,
                'finalised' => $finalisedAssessment,
                'status_label' => ! $assessment instanceof PlanAssessment
                    ? ($plan instanceof BusinessPlan
                        && in_array($plan->status, [BusinessPlan::STATUS_SUBMITTED, BusinessPlan::STATUS_ASSESSING], true)
                        ? 'Awaiting advisor review'
                        : 'Not started')
                    : ($finalisedAssessment ? 'Feedback ready' : 'Advisor review'),
            ],
            'advisory' => [
                'available' => $advisoryAvailable && ! ($foundingAdvisory instanceof FoundingAdvisoryEngagement),
                'label' => match (true) {
                    $foundingAdvisory?->status === FoundingAdvisoryEngagement::STATUS_PROPOSAL_SENT && $proposalReleased => 'Your advisory proposal is ready to review',
                    $foundingAdvisory instanceof FoundingAdvisoryEngagement => 'Your advisory implementation journey is active',
                    $advisoryAvailable => 'Advisory services are ready to request',
                    default => 'Available when BP&B meets its minimum requirements and your advisor approves the assessment',
                },
                'url' => $foundingAdvisory instanceof FoundingAdvisoryEngagement
                    ? route('portal.entrepreneur.dashboard', ['journey' => 'advisory'], absolute: false)
                    : route('portal.entrepreneur.advisory-services.show', absolute: false),
            ],
            'next' => $next,
            'tabs' => [
                'advisory' => $foundingAdvisory instanceof FoundingAdvisoryEngagement,
                'idea_validation' => $validation instanceof IdeaValidation || $includesIdeaValidation,
                'plan_budget' => $includesPlanBudget,
            ],
        ];
    }

    /** @return array{total:int,completed:int,percent:int} */
    private function completion(BusinessPlan $plan): array
    {
        $requirements = $this->requirements->payload($plan);
        $rows = collect($requirements)->flatten(1);
        $total = $rows->count();
        $completed = $rows->filter(fn (array $requirement): bool => $requirement['complete'])->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }
}
