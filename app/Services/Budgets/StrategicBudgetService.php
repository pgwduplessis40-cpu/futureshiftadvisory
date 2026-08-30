<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EconomicIndicator;
use App\Models\FinancialSnapshot;
use App\Models\Message;
use App\Models\Proposal;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Models\StrategicBudget;
use App\Models\StrategicBudgetAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Messaging\MessageThreadService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StrategicBudgetService
{
    private const PLAN_SECTION_KEYS = [
        'goals',
        'current_position',
        'market_customers',
        'operations',
        'risks',
        'swot',
        'action_priorities',
        'evidence_documents',
    ];

    private const FINANCIAL_KEYWORDS = [
        'p&l',
        'p and l',
        'profit and loss',
        'profit-loss',
        'profit_loss',
        'management accounts',
        'management_accounts',
        'management-account',
    ];

    private const AUTOMATIC_SCENARIO_KEYS = [
        'revenue_downside',
        'cost_upside',
        'combined_downside',
    ];

    public function __construct(
        private readonly BudgetCalculator $calculator,
        private readonly AuditWriter $audit,
        private readonly MessageThreadService $messages,
    ) {}

    public function ensureForClient(Client $client, ?BusinessPlan $plan = null): StrategicBudget
    {
        $pathway = $this->pathway($client);
        $financials = $this->financialDocuments($client);
        $unlocked = $financials->isNotEmpty();
        $budget = StrategicBudget::query()->firstOrNew([
            'client_id' => $client->getKey(),
            'pathway' => $pathway,
        ]);
        $existingStatus = (string) ($budget->status ?: StrategicBudget::STATUS_LOCKED);

        if (! $budget->exists) {
            $budget->forceFill([
                'label' => $this->label($pathway),
                'status' => $unlocked ? StrategicBudget::STATUS_SYSTEM_DRAFT : StrategicBudget::STATUS_LOCKED,
                'horizon_months' => $this->defaultHorizonMonths($client),
                'source_financials' => $this->sourceFinancialsPayload($financials),
                'client_goals' => $this->clientGoals($client),
                'advisor_goals' => [],
                'business_plan_sections' => [],
                'business_plan_source_drafts' => [],
                'business_plan_prompts' => [],
                'assumptions' => [],
                'implementation_costs' => [],
                'monthly_fixed_costs' => [],
                'future_costs' => [],
                'revenue_forecast' => [],
                'funding_sources' => [],
                'funding_scenarios' => [],
                'computed' => [],
                'flags' => [],
                'confidence' => [],
            ]);
        }

        $status = $existingStatus;
        if ($unlocked && $existingStatus === StrategicBudget::STATUS_LOCKED) {
            $status = StrategicBudget::STATUS_SYSTEM_DRAFT;
        }
        if (! $unlocked) {
            $status = StrategicBudget::STATUS_LOCKED;
        }

        $budget->forceFill([
            'business_plan_id' => $plan?->getKey() ?? $budget->business_plan_id,
            'label' => $this->label($pathway),
            'status' => $status,
            'horizon_months' => (int) ($budget->horizon_months ?: $this->defaultHorizonMonths($client)),
            'source_financials' => $this->sourceFinancialsPayload($financials),
            'client_goals' => $this->clientGoals($client),
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_prompts' => $this->businessPlanPrompts($pathway),
            'business_plan_source_drafts' => $this->sourceDrafts($client, $plan, $pathway),
            'business_plan_sections' => $this->normaliseBusinessPlanSections(
                (array) ($budget->business_plan_sections ?? []),
                $pathway,
            ),
        ])->save();

        $budget = $budget->refresh();

        if ($budget->isUnlocked() && ($budget->computed ?? []) === []) {
            $this->recompute($budget);
        } else {
            $this->refreshReadiness($budget);
        }

        return $budget->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(StrategicBudget $budget, array $input, User $actor): StrategicBudget
    {
        return DB::transaction(function () use ($budget, $input, $actor): StrategicBudget {
            $status = in_array($budget->status, [
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true)
                ? StrategicBudget::STATUS_CLIENT_WORKING_DRAFT
                : (string) ($budget->status ?: StrategicBudget::STATUS_CLIENT_WORKING_DRAFT);

            if ($status === StrategicBudget::STATUS_SYSTEM_DRAFT) {
                $status = StrategicBudget::STATUS_CLIENT_WORKING_DRAFT;
            }

            $updates = [
                'status' => $status,
                'business_plan_sections' => $this->normaliseBusinessPlanSections(
                    (array) ($input['business_plan_sections'] ?? $budget->business_plan_sections ?? []),
                    (string) $budget->pathway,
                ),
                'business_plan_prompts' => $this->businessPlanPrompts((string) $budget->pathway),
                'business_plan_submitted_at' => null,
                'business_plan_approved_at' => null,
                'business_plan_approved_by_user_id' => null,
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
            ];

            if ($budget->isUnlocked()) {
                $updates = [
                    ...$updates,
                    'horizon_months' => $this->horizonMonths($input['horizon_months'] ?? $budget->horizon_months),
                    'expected_runway_months' => $this->expectedRunway($input['expected_runway_months'] ?? null),
                    'assumptions' => (array) ($input['assumptions'] ?? []),
                    'implementation_costs' => $this->calculator->normaliseRows((array) ($input['implementation_costs'] ?? [])),
                    'monthly_fixed_costs' => $this->calculator->normaliseRows((array) ($input['monthly_fixed_costs'] ?? [])),
                    'future_costs' => $this->calculator->normaliseFutureCosts((array) ($input['future_costs'] ?? [])),
                    'revenue_forecast' => $this->calculator->normaliseRows((array) ($input['revenue_forecast'] ?? [])),
                    'funding_sources' => $this->calculator->normaliseRows((array) ($input['funding_sources'] ?? [])),
                    'funding_scenarios' => $this->calculator->normaliseFundingScenarios((array) ($input['funding_scenarios'] ?? [])),
                ];
            }

            $budget->forceFill($updates)->save();

            $budget = $budget->isUnlocked()
                ? $this->recompute($budget->refresh())
                : $this->refreshReadiness($budget->refresh());

            $this->audit->record('strategic_budget.updated', subject: $budget, actor: $actor, after: [
                'client_id' => $budget->client_id,
                'pathway' => $budget->pathway,
                'status' => $budget->status,
                'horizon_months' => $budget->horizon_months,
                'confidence_score' => data_get($budget->confidence, 'score'),
            ]);

            return $budget->refresh();
        });
    }

    public function submit(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);

        if ($this->reviewSubmittedOrLater($budget)) {
            return $budget->refresh();
        }

        abort_unless($this->businessPlanReady($budget), 422);

        $budget = $this->recompute($budget);
        $submittedAt = now();
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            'submitted_at' => $submittedAt,
            'business_plan_submitted_at' => $submittedAt,
            'approved_at' => null,
            'approved_by_user_id' => null,
            'business_plan_approved_at' => null,
            'business_plan_approved_by_user_id' => null,
        ])->save();

        $assessment = $this->createAssessmentVersion($budget->refresh(), $actor, StrategicBudgetAssessment::STATUS_SUBMITTED);

        $this->audit->record('strategic_budget.submitted', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
            'assessment_id' => $assessment->getKey(),
            'version' => $assessment->round,
        ]);

        return $budget->refresh();
    }

    public function approve(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);
        abort_unless($this->reviewSubmittedOrLater($budget), 422);
        abort_unless($this->latestAssessmentForCurrentSubmission($budget)?->assessed_at !== null, 422);

        $budget = $this->recompute($budget);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_ADVISOR_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $actor->getKey(),
            'business_plan_approved_at' => now(),
            'business_plan_approved_by_user_id' => $actor->getKey(),
        ])->save();

        $this->markLatestAssessmentApproved($budget->refresh(), $actor);

        $this->audit->record('strategic_budget.approved', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
        ]);

        return $budget->refresh();
    }

    public function assess(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);
        abort_unless($this->reviewSubmittedOrLater($budget), 422);

        $before = [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'confidence_score' => data_get($budget->confidence, 'score'),
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
        ];

        $budget = $this->recompute($budget);
        $assessment = $this->recordAssessmentRun($budget->refresh(), $actor);

        $this->audit->record('strategic_budget.assessed', subject: $budget, actor: $actor, before: $before, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'confidence_score' => data_get($budget->confidence, 'score'),
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
            'active_flags' => count((array) ($budget->flags ?? [])),
            'assessment_id' => $assessment->getKey(),
            'version' => $assessment->round,
        ]);

        return $budget->refresh();
    }

    public function saveAssessmentFeedback(
        StrategicBudget $budget,
        User $actor,
        string $advisorFeedback,
        string $proposedReply,
        bool $sendToClient,
    ): StrategicBudgetAssessment {
        abort_unless($budget->isUnlocked(), 422);

        $assessment = $this->latestAssessmentForCurrentSubmission($budget)
            ?? $this->latestAssessment($budget);

        abort_unless($assessment instanceof StrategicBudgetAssessment && $assessment->assessed_at !== null, 422);

        $advisorFeedback = trim($advisorFeedback);
        $proposedReply = trim($proposedReply);
        $suggestions = [
            'suggested_feedback' => (string) ($assessment->suggested_feedback ?: $this->suggestedAdvisorFeedback(
                $budget,
                (array) ($assessment->assessment_criteria ?? []),
                (array) ($assessment->priorities ?? []),
            )),
            'suggested_reply' => (string) ($assessment->suggested_reply ?: $this->suggestedClientReply(
                $budget,
                (array) ($assessment->assessment_criteria ?? []),
                (array) ($assessment->priorities ?? []),
            )),
        ];
        $feedbackSnapshot = $this->feedbackSnapshotWithEdits(
            assessment: $assessment,
            suggestions: $suggestions,
            advisorFeedback: $advisorFeedback,
            proposedReply: $proposedReply,
            sentToClient: $sendToClient,
            actor: $actor,
        );

        $message = null;
        if ($sendToClient) {
            $message = $this->messages->startClientThread(
                client: $budget->client()->firstOrFail(),
                sender: $actor,
                subject: 'Business Plan & Budget assessment feedback',
                body: $proposedReply,
            );
        }
        $messageThreadId = $message instanceof Message ? $message->thread_id : null;
        $messageId = $message instanceof Message ? $message->getKey() : null;

        $assessment->forceFill([
            'status' => $sendToClient
                ? StrategicBudgetAssessment::STATUS_FEEDBACK_SENT
                : StrategicBudgetAssessment::STATUS_FEEDBACK_SAVED,
            'advisor_feedback' => $advisorFeedback,
            'proposed_reply' => $proposedReply,
            'suggested_feedback' => $suggestions['suggested_feedback'],
            'suggested_reply' => $suggestions['suggested_reply'],
            'feedback_snapshot' => $feedbackSnapshot,
            'feedback_saved_at' => now(),
            'feedback_saved_by_user_id' => $actor->getKey(),
            'feedback_sent_at' => $sendToClient ? now() : $assessment->feedback_sent_at,
            'feedback_sent_by_user_id' => $sendToClient ? $actor->getKey() : $assessment->feedback_sent_by_user_id,
            'client_message_thread_id' => $messageThreadId ?? $assessment->client_message_thread_id,
            'client_message_id' => $messageId ?? $assessment->client_message_id,
        ])->save();

        $this->audit->record(
            $sendToClient
                ? 'strategic_budget.assessment_feedback_sent'
                : 'strategic_budget.assessment_feedback_saved',
            subject: $assessment,
            actor: $actor,
            after: [
                'strategic_budget_id' => $budget->getKey(),
                'client_id' => $budget->client_id,
                'version' => $assessment->round,
                'feedback_changed_from_suggestion' => data_get($feedbackSnapshot, 'advisor_edits.feedback_changed_from_suggestion'),
                'proposed_reply_changed_from_suggestion' => data_get($feedbackSnapshot, 'advisor_edits.proposed_reply_changed_from_suggestion'),
                'client_message_thread_id' => $messageThreadId,
            ],
        );

        return $assessment->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    public function updateAdvisorGoals(StrategicBudget $budget, array $goals, User $actor): StrategicBudget
    {
        $normalised = collect($goals)
            ->filter(fn (mixed $goal): bool => is_array($goal))
            ->map(fn (array $goal): array => [
                'title' => trim((string) ($goal['title'] ?? '')),
                'measure' => trim((string) ($goal['measure'] ?? '')),
            ])
            ->filter(fn (array $goal): bool => $goal['title'] !== '' || $goal['measure'] !== '')
            ->values()
            ->all();

        $budget->forceFill(['advisor_goals' => $normalised])->save();
        $this->audit->record('strategic_budget.advisor_goals_updated', subject: $budget, actor: $actor, after: [
            'goal_count' => count($normalised),
        ]);

        return $this->refreshReadiness($budget->refresh());
    }

    public function markUsedInProposal(StrategicBudget $budget, Proposal $proposal, User $actor): StrategicBudget
    {
        if (! $budget->isApprovedForProposal()) {
            return $budget->refresh();
        }

        $budget->forceFill([
            'status' => StrategicBudget::STATUS_USED_IN_PROPOSAL,
            'proposal_id' => $proposal->getKey(),
            'used_in_proposal_at' => now(),
        ])->save();

        $this->audit->record('strategic_budget.used_in_proposal', subject: $budget, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
        ]);

        return $budget->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPayload(StrategicBudget $budget): array
    {
        $budgetPackAvailable = $budget->pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? $budget->isUnlocked()
            : $budget->accepted_snapshot_at !== null;

        return [
            ...$this->basePayload($budget),
            'update_url' => route('portal.business-plan-budget.update', absolute: false),
            'submit_url' => route('portal.business-plan-budget.submit', absolute: false),
            'export_url' => route('portal.business-plan-budget.export', absolute: false),
            'budget_pack_available' => $budgetPackAvailable,
            'budget_pack_locked_reason' => ! $budgetPackAvailable
                ? $this->budgetPackLockedReason($budget)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function advisorPayload(StrategicBudget $budget): array
    {
        return [
            ...$this->basePayload($budget),
            'approve_url' => route('advisor.clients.strategic-budget.approve', $budget->client_id, absolute: false),
            'run_assessment_url' => route('advisor.clients.strategic-budget.assess', $budget->client_id, absolute: false),
            'can_run_assessment' => $budget->isUnlocked()
                && $this->businessPlanReady($budget)
                && $this->reviewSubmittedOrLater($budget),
            'assessment_ready_for_approval' => $this->latestAssessmentForCurrentSubmission($budget)?->assessed_at !== null,
            'assessment_action_label' => $this->reviewApprovedOrLater($budget)
                ? 'Run reassessment'
                : 'Run assessment',
            'assessment_feedback' => $this->assessmentFeedbackPayload($budget),
            'assessment_history' => $this->assessmentHistoryPayload($budget),
            'advisor_goals_url' => route('advisor.clients.strategic-budget.advisor-goals', $budget->client_id, absolute: false),
        ];
    }

    private function budgetPackLockedReason(StrategicBudget $budget): string
    {
        if ($budget->pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE) {
            return 'Upload and verify a P&L or management accounts file to unlock the Budget PDF.';
        }

        return 'Budget Pack PDF unlocks automatically after the proposal is accepted.';
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalGuardPayload(StrategicBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'approved' => $budget->isApprovedForProposal(),
            'confidence_score' => (int) data_get($budget->confidence, 'score', 0),
            'warning' => $budget->isApprovedForProposal()
                ? null
                : $budget->label.' has not been advisor-approved. Generating a proposal now requires a hard acknowledgement override.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsPayload(StrategicBudget $budget): array
    {
        $computed = $this->computedForRead($budget);
        $confidence = (array) ($budget->confidence ?? []);
        $annualForecast = $this->annualForecastRows($computed);
        $monthlyForecast = $this->monthlyForecastRows($computed);
        $scenarioRows = $this->scenarioRows($computed);
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $firstYear = $annualForecast[0] ?? [];
        $rowConfidence = (array) data_get($confidence, 'row_confidence', []);
        $costDrivers = $this->costDrivers($budget);
        $missingAssumptions = $this->missingAssumptions($computed);
        $flags = (array) ($budget->flags ?? []);
        $prescriptiveActions = $this->prescriptiveActions($budget, $flags, $computed, $confidence);
        $yearOneRevenue = (float) ($firstYear['revenue'] ?? 0);
        $yearOneFixedCosts = (float) ($firstYear['fixed_costs'] ?? 0);
        $totalFunding = (float) data_get($computed, 'total_funding', 0);
        $runwayText = $this->runwayText($computed);
        $breakEvenText = $this->yearText(data_get($computed, 'break_even_year'));
        $cashFlowPositiveText = $this->yearText(data_get($computed, 'cash_flow_positive_year'));
        $knownRows = (int) ($rowConfidence['known'] ?? 0);
        $estimateRows = (int) ($rowConfidence['estimate'] ?? 0);
        $guessRows = (int) ($rowConfidence['guess'] ?? 0);
        $evidenceText = sprintf(
            '%s known, %s %s, %s %s',
            $knownRows,
            $estimateRows,
            $this->plural('estimate', $estimateRows),
            $guessRows,
            $this->plural('guess', $guessRows),
        );
        $topDriver = collect($costDrivers)->first(fn (array $driver): bool => (float) ($driver['value'] ?? 0) > 0);
        $firstFlag = collect($flags)->first(fn (mixed $flag): bool => is_array($flag));
        $planSections = collect((array) ($budget->business_plan_sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->values();
        $completedPlanSections = $planSections
            ->filter(fn (array $section): bool => trim((string) ($section['answer'] ?? '')) !== '')
            ->count();
        $planSectionTotal = max(1, $planSections->count());
        $planCoverage = "{$completedPlanSections}/{$planSectionTotal} plan sections complete";
        $goalExcerpt = $this->sectionExcerpt($planSections->all(), 'goals');
        $riskExcerpt = $this->sectionExcerpt($planSections->all(), 'risks');
        $actionExcerpt = $this->sectionExcerpt($planSections->all(), 'action_priorities');
        $descriptions = array_values(array_filter([
            "Plan coverage: {$planCoverage}",
            $goalExcerpt ? "Plan goal: {$goalExcerpt}" : null,
            "Year 1 revenue: {$this->money($yearOneRevenue)}",
            "Year 1 fixed costs: {$this->money($yearOneFixedCosts)}",
            "Funding available: {$this->money($totalFunding)}",
            "Runway: {$runwayText}",
        ]));
        $diagnoses = collect($flags)
            ->filter(fn (mixed $flag): bool => is_array($flag))
            ->map(fn (array $flag): string => (string) ($flag['title'] ?? 'Budget warning').': '.(string) ($flag['message'] ?? 'Review this budget signal.'))
            ->values()
            ->all();
        $diagnoses = array_values(array_filter([
            ...$diagnoses,
            $riskExcerpt ? "Plan risk noted: {$riskExcerpt}" : null,
            count($missingAssumptions) > 0 ? count($missingAssumptions).' missing budget assumption'.(count($missingAssumptions) === 1 ? '' : 's') : 'No missing budget assumptions',
            "Evidence base: {$evidenceText}",
            $topDriver ? 'Largest cost driver: '.(string) ($topDriver['label'] ?? 'Cost driver').' at '.$this->money((float) ($topDriver['value'] ?? 0)) : null,
        ]));
        $predictions = array_values(array_filter([
            "Break-even is {$breakEvenText}.",
            "Cash-flow positive timing is {$cashFlowPositiveText}.",
            $scenarioRows !== [] ? 'Base scenario ending cash is '.$this->money((float) ($scenarioRows[0]['ending_cash'] ?? 0)).'.' : null,
        ]));
        $prescriptions = collect($prescriptiveActions)
            ->take(3)
            ->map(fn (array $action): string => ucfirst((string) ($action['priority'] ?? 'medium')).': '.(string) ($action['action'] ?? 'Review this budget signal.'))
            ->values()
            ->all();
        $prescriptions = array_values(array_filter([
            $actionExcerpt ? "Plan action priority to fund: {$actionExcerpt}" : null,
            ...$prescriptions,
        ]));

        return [
            'descriptive' => [
                'summary' => (bool) ($sourceFinancials['unlocked'] ?? false)
                    ? "Plan coverage is {$planCoverage}; Year 1 is forecasting {$this->money($yearOneRevenue)} revenue, {$this->money($yearOneFixedCosts)} fixed costs, {$this->money($totalFunding)} funding available, and {$runwayText} runway."
                    : 'Budget is locked until a verified P&L or management accounts file is available.',
                'explanation' => 'Current budget view based on uploaded financial evidence and client-entered budget assumptions.',
                'findings' => $descriptions,
                'metrics' => [
                    $this->metric('Year 1 revenue', (float) ($firstYear['revenue'] ?? 0), 'currency'),
                    $this->metric('Year 1 fixed costs', (float) ($firstYear['fixed_costs'] ?? 0), 'currency'),
                    $this->metric('Funding available', (float) data_get($computed, 'total_funding', 0), 'currency'),
                    $this->metric('Runway', data_get($computed, 'runway_open_ended') ? 'Open ended' : data_get($computed, 'runway_months'), 'months'),
                ],
                'source_financials' => $sourceFinancials,
            ],
            'diagnostic' => [
                'summary' => $flags === []
                    ? "No active budget warnings are present; evidence mix is {$evidenceText}."
                    : count($flags).' active budget warning'.(count($flags) === 1 ? '' : 's').': '.(string) ($firstFlag['title'] ?? 'Review budget risk').'.',
                'explanation' => 'Explains why the budget is strong, weak, incomplete, or risky.',
                'findings' => $diagnoses,
                'flags' => $flags,
                'cost_drivers' => $costDrivers,
                'missing_assumptions' => $missingAssumptions,
                'confidence_mix' => [
                    'known' => $knownRows,
                    'estimate' => $estimateRows,
                    'guess' => $guessRows,
                    'total' => (int) ($rowConfidence['total'] ?? 0),
                ],
            ],
            'predictive' => [
                'summary' => "Runway is {$runwayText}.",
                'explanation' => 'Projects runway, break-even timing, cash-flow timing, and scenario outcomes.',
                'findings' => $predictions,
                'key_events' => [
                    $this->metric('Break-even', data_get($computed, 'break_even_year'), 'year'),
                    $this->metric('Cash-flow positive', data_get($computed, 'cash_flow_positive_year'), 'year'),
                    $this->metric('Runway', data_get($computed, 'runway_open_ended') ? 'Open ended' : data_get($computed, 'runway_months'), 'months'),
                ],
                'annual_forecast' => $annualForecast,
                'monthly_forecast' => $monthlyForecast,
                'scenarios' => $scenarioRows,
            ],
            'prescriptive' => [
                'summary' => 'Next action: '.(string) ($prescriptiveActions[0]['action'] ?? 'Maintain the current budget and proceed to advisor review when the plan is complete.'),
                'explanation' => 'Turns budget signals into advisor/client actions before proposal reliance.',
                'findings' => $prescriptions,
                'actions' => $prescriptiveActions,
                'advisor_decision_points' => [
                    'Confirm whether the financial upload is sufficient evidence for proposal reliance.',
                    'Check whether guessed rows need client confirmation or advisor-reviewed estimates.',
                    'Confirm that funding, runway, and break-even timing support the proposed engagement and payment terms.',
                ],
            ],
            'charts' => [
                'annual_revenue_costs' => $this->annualChartRows($annualForecast),
                'margin_percentages' => $this->marginChartRows($annualForecast, $computed, (array) ($budget->assumptions ?? [])),
                'monthly_cash' => $this->monthlyChartRows($monthlyForecast),
                'scenario_comparison' => $scenarioRows,
                'confidence_mix' => [
                    ['label' => 'Known', 'value' => $knownRows],
                    ['label' => 'Estimate', 'value' => $estimateRows],
                    ['label' => 'Guess', 'value' => $guessRows],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(StrategicBudget $budget): array
    {
        $computed = $this->computedForRead($budget);
        $confidence = (array) ($budget->confidence ?? []);
        $reviewApprovedOrLater = $this->reviewApprovedOrLater($budget);
        $reviewSubmittedOrLater = $this->reviewSubmittedOrLater($budget);

        return [
            'id' => $budget->id,
            'label' => $budget->label,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'locked' => ! $budget->isUnlocked(),
            'horizon_months' => $budget->horizon_months,
            'expected_runway_months' => $budget->expected_runway_months,
            'source_financials' => $budget->source_financials ?? [],
            'client_goals' => $budget->client_goals ?? [],
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_sections' => $budget->business_plan_sections ?? [],
            'business_plan_source_drafts' => $budget->business_plan_source_drafts ?? [],
            'business_plan_prompts' => $budget->business_plan_prompts ?? [],
            'business_plan_readiness_score' => $this->businessPlanReadiness($budget),
            'business_plan_ready' => $this->businessPlanReady($budget),
            'business_plan_submitted_at' => $budget->business_plan_submitted_at?->toIso8601String(),
            'business_plan_approved_at' => $budget->business_plan_approved_at?->toIso8601String(),
            'assumptions' => $budget->assumptions ?? [],
            'implementation_costs' => $budget->implementation_costs ?? [],
            'monthly_fixed_costs' => $budget->monthly_fixed_costs ?? [],
            'future_costs' => $budget->future_costs ?? [],
            'revenue_forecast' => $budget->revenue_forecast ?? [],
            'funding_sources' => $budget->funding_sources ?? [],
            'funding_scenarios' => $budget->funding_scenarios ?? [],
            'computed' => $computed,
            'flags' => $budget->flags ?? [],
            'confidence' => $confidence,
            'analytics' => $this->analyticsPayload($budget),
            'assessment_criteria' => $this->assessmentCriteria($budget, $computed, $confidence),
            'readiness_score' => (int) data_get($confidence, 'score', 0),
            'progress_score' => (int) data_get($confidence, 'progress_score', 0),
            'submitted_at' => $budget->submitted_at?->toIso8601String(),
            'approved_at' => $budget->approved_at?->toIso8601String(),
            'can_submit_for_review' => $budget->isUnlocked() && ! $reviewSubmittedOrLater,
            'review_submitted_or_later' => $reviewSubmittedOrLater,
            'review_approved_or_later' => $reviewApprovedOrLater,
            'review_action_label' => $reviewApprovedOrLater
                ? 'Advisor approved'
                : ($reviewSubmittedOrLater ? 'Submitted for review' : 'Submit for review'),
            'used_in_proposal_at' => $budget->used_in_proposal_at?->toIso8601String(),
            'accepted_snapshot_at' => $budget->accepted_snapshot_at?->toIso8601String(),
        ];
    }

    private function recompute(StrategicBudget $budget): StrategicBudget
    {
        if (! $budget->isUnlocked()) {
            return $this->refreshReadiness($budget);
        }

        $computed = $this->calculate($budget);
        $confidence = $this->confidence($budget, $computed);
        $flags = $this->flags($budget, $computed, $confidence);

        $budget->forceFill([
            'computed' => $computed,
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(StrategicBudget $budget): array
    {
        return $this->calculator->compute(
            launchCosts: (array) ($budget->implementation_costs ?? []),
            monthlyFixedCosts: (array) ($budget->monthly_fixed_costs ?? []),
            revenueForecast: (array) ($budget->revenue_forecast ?? []),
            fundingSources: (array) ($budget->funding_sources ?? []),
            expectedRunwayMonths: $budget->expected_runway_months,
            forecastYears: max(1, (int) ceil(((int) $budget->horizon_months) / 12)),
            assumptions: (array) ($budget->assumptions ?? []),
            futureCosts: (array) ($budget->future_costs ?? []),
            fundingScenarios: (array) ($budget->funding_scenarios ?? []),
            companyTaxRatePercent: $this->economicPercent(EconomicIndicator::COMPANY_TAX_RATE),
            defaultCostInflationPercent: $this->economicPercent(EconomicIndicator::CPI_ANNUAL),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function computedForRead(StrategicBudget $budget): array
    {
        $computed = (array) ($budget->computed ?? []);

        if ($this->computedNeedsScenarioRefresh($computed) && $this->hasBudgetInputs($budget)) {
            return $this->calculate($budget);
        }

        return $computed;
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function computedNeedsScenarioRefresh(array $computed): bool
    {
        $keys = collect((array) data_get($computed, 'scenarios', []))
            ->filter(fn (mixed $scenario): bool => is_array($scenario))
            ->map(fn (array $scenario): string => (string) ($scenario['key'] ?? ''))
            ->filter()
            ->values()
            ->all();

        foreach (self::AUTOMATIC_SCENARIO_KEYS as $requiredKey) {
            if (! in_array($requiredKey, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasBudgetInputs(StrategicBudget $budget): bool
    {
        foreach ([
            $budget->implementation_costs,
            $budget->monthly_fixed_costs,
            $budget->future_costs,
            $budget->revenue_forecast,
            $budget->funding_sources,
            $budget->funding_scenarios,
        ] as $rows) {
            if ((array) $rows !== []) {
                return true;
            }
        }

        return (array) ($budget->assumptions ?? []) !== [];
    }

    /**
     * @return array{label:string,value:mixed,format:string,detail:?string}
     */
    private function metric(string $label, mixed $value, string $format = 'number', ?string $detail = null): array
    {
        return compact('label', 'value', 'format', 'detail');
    }

    private function money(float $value): string
    {
        return 'NZ$'.number_format($value, 0);
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function runwayText(array $computed): string
    {
        if ((bool) data_get($computed, 'runway_open_ended', false)) {
            return 'open ended';
        }

        $months = data_get($computed, 'runway_months');

        return is_numeric($months) ? ((int) $months).' months' : 'not yet known';
    }

    private function yearText(mixed $year): string
    {
        return is_numeric($year) && (int) $year > 0 ? 'Year '.(int) $year : 'not yet visible';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function sectionExcerpt(array $sections, string $key): ?string
    {
        $section = collect($sections)->first(
            fn (array $section): bool => (string) ($section['key'] ?? '') === $key,
        );

        if (! is_array($section)) {
            return null;
        }

        $answer = trim((string) ($section['answer'] ?? ''));

        return $answer === '' ? null : str($answer)->squish()->limit(150)->toString();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function annualForecastRows(array $computed): array
    {
        return collect((array) data_get($computed, 'annual_totals', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'year' => (int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'variable_costs' => (float) ($row['variable_costs'] ?? 0),
                'fixed_costs' => (float) ($row['fixed_costs'] ?? 0),
                'interest' => (float) ($row['interest'] ?? 0),
                'tax' => (float) ($row['tax'] ?? 0),
                'loan_principal' => (float) ($row['loan_principal'] ?? 0),
                'funding_inflow' => (float) ($row['funding_inflow'] ?? 0),
                'launch_costs' => (float) ($row['launch_costs'] ?? 0),
                'gross_profit' => (float) ($row['gross_profit'] ?? 0),
                'net_profit_before_tax' => (float) ($row['net_profit_before_tax'] ?? 0),
                'net_profit_after_tax' => (float) ($row['net_profit_after_tax'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
                'ending_cash' => (float) ($row['ending_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function monthlyForecastRows(array $computed): array
    {
        return collect((array) data_get($computed, 'monthly_detail', data_get($computed, 'monthly_series', [])))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'month' => (int) ($row['month'] ?? 0),
                'month_in_year' => (int) ($row['month_in_year'] ?? 0),
                'year' => (int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'variable_costs' => (float) ($row['variable_costs'] ?? 0),
                'fixed_costs' => (float) ($row['fixed_costs'] ?? 0),
                'interest' => (float) ($row['interest'] ?? 0),
                'tax' => (float) ($row['tax'] ?? 0),
                'loan_principal' => (float) ($row['loan_principal'] ?? 0),
                'funding_inflow' => (float) ($row['funding_inflow'] ?? 0),
                'launch_costs' => (float) ($row['launch_costs'] ?? 0),
                'net_profit_after_tax' => (float) ($row['net_profit_after_tax'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
                'cumulative_cash' => (float) ($row['cumulative_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function scenarioRows(array $computed): array
    {
        return collect((array) data_get($computed, 'scenarios', []))
            ->filter(fn (mixed $scenario): bool => is_array($scenario))
            ->map(function (array $scenario): array {
                $annualRows = collect((array) ($scenario['annual_totals'] ?? []))
                    ->filter(fn (mixed $row): bool => is_array($row));
                $lastYear = $annualRows->last();
                $summary = (array) ($scenario['summary'] ?? []);

                return [
                    'key' => (string) ($scenario['key'] ?? ''),
                    'name' => (string) ($scenario['name'] ?? 'Scenario'),
                    'type' => (string) ($scenario['type'] ?? ''),
                    'runway_months' => $summary['runway_months'] ?? null,
                    'runway_open_ended' => (bool) ($summary['runway_open_ended'] ?? false),
                    'break_even_year' => $summary['break_even_year'] ?? null,
                    'cash_flow_positive_year' => $summary['cash_flow_positive_year'] ?? null,
                    'total_funding' => (float) ($summary['total_funding'] ?? 0),
                    'automatic' => (bool) ($scenario['automatic'] ?? false),
                    'sensitivity' => (array) ($scenario['sensitivity'] ?? []),
                    'ending_cash' => is_array($lastYear) ? (float) ($lastYear['ending_cash'] ?? 0) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $annualForecast
     * @return array<int, array<string, mixed>>
     */
    private function annualChartRows(array $annualForecast): array
    {
        return collect($annualForecast)
            ->map(fn (array $row): array => [
                'label' => 'Year '.(int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'costs' => (float) ($row['variable_costs'] ?? 0)
                    + (float) ($row['fixed_costs'] ?? 0)
                    + (float) ($row['interest'] ?? 0)
                    + (float) ($row['tax'] ?? 0)
                    + (float) ($row['loan_principal'] ?? 0)
                    + (float) ($row['launch_costs'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $annualForecast
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $storedAssumptions
     * @return array<int, array<string, mixed>>
     */
    private function marginChartRows(array $annualForecast, array $computed, array $storedAssumptions): array
    {
        $assumptions = array_replace($storedAssumptions, (array) data_get($computed, 'assumptions', []));

        return collect($annualForecast)
            ->map(function (array $row) use ($assumptions): array {
                return [
                    'label' => 'Year '.(int) ($row['year'] ?? 0),
                    'gross_profit_percent' => $this->marginPercent(
                        (float) ($row['gross_profit'] ?? 0),
                        (float) ($row['revenue'] ?? 0),
                    ),
                    'net_profit_before_tax_percent' => $this->marginPercent(
                        (float) ($row['net_profit_before_tax'] ?? 0),
                        (float) ($row['revenue'] ?? 0),
                    ),
                    'net_profit_after_tax_percent' => $this->marginPercent(
                        (float) ($row['net_profit_after_tax'] ?? 0),
                        (float) ($row['revenue'] ?? 0),
                    ),
                    'target_gross_profit_percent' => $this->nullableChartPercent($assumptions['target_gross_profit_percent'] ?? null),
                    'target_net_profit_before_tax_percent' => $this->nullableChartPercent($assumptions['target_net_profit_before_tax_percent'] ?? null),
                    'target_net_profit_after_tax_percent' => $this->nullableChartPercent($assumptions['target_net_profit_after_tax_percent'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function nullableChartPercent(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function marginPercent(float $profit, float $revenue): float
    {
        if ($revenue === 0.0) {
            return 0.0;
        }

        return round(($profit / $revenue) * 100, 1);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyForecast
     * @return array<int, array<string, mixed>>
     */
    private function monthlyChartRows(array $monthlyForecast): array
    {
        return collect($monthlyForecast)
            ->take(36)
            ->map(fn (array $row): array => [
                'month' => (int) ($row['month'] ?? 0),
                'month_in_year' => (int) ($row['month_in_year'] ?? 0),
                'label' => 'M'.(int) ($row['month'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'costs' => (float) ($row['variable_costs'] ?? 0)
                    + (float) ($row['fixed_costs'] ?? 0)
                    + (float) ($row['interest'] ?? 0)
                    + (float) ($row['tax'] ?? 0)
                    + (float) ($row['loan_principal'] ?? 0)
                    + (float) ($row['launch_costs'] ?? 0),
                'cumulative_cash' => (float) ($row['cumulative_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,value:float}>
     */
    private function costDrivers(StrategicBudget $budget): array
    {
        return collect([
            ['label' => 'Implementation costs', 'value' => $this->inputRowsTotal((array) ($budget->implementation_costs ?? []))],
            ['label' => 'Monthly fixed costs', 'value' => $this->inputRowsTotal((array) ($budget->monthly_fixed_costs ?? []))],
            ['label' => 'Future costs', 'value' => $this->inputRowsTotal((array) ($budget->future_costs ?? []))],
        ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function inputRowsTotal(array $rows): float
    {
        return round(array_reduce(
            $rows,
            fn (float $total, array $row): float => $total
                + ((float) ($row['amount'] ?? 0) * max(1.0, (float) ($row['quantity'] ?? 1))),
            0.0,
        ), 2);
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array{key:string,label:string}>
     */
    private function missingAssumptions(array $computed): array
    {
        $labels = (array) data_get($computed, 'assumptions.field_labels', []);

        return collect((array) ($computed['missing_assumptions'] ?? []))
            ->map(function (mixed $key) use ($labels): array {
                $key = (string) $key;

                return [
                    'key' => $key,
                    'label' => (string) ($labels[$key] ?? str($key)->replace('_', ' ')->title()->toString()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $flags
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array<string, string>>
     */
    private function prescriptiveActions(StrategicBudget $budget, array $flags, array $computed, array $confidence): array
    {
        $actions = collect($flags)
            ->filter(fn (mixed $flag): bool => is_array($flag))
            ->map(function (array $flag): array {
                $key = (string) ($flag['key'] ?? '');

                return [
                    'priority' => (string) ($flag['severity'] ?? 'medium'),
                    'action' => match ($key) {
                        'financial_upload_required' => 'Upload and verify a P&L or management accounts file before relying on this budget.',
                        'partial_financials' => 'Request an additional financial upload to strengthen the evidence base.',
                        'business_plan_incomplete' => 'Complete every plan section before advisor approval.',
                        'implementation_costs_missing' => 'Add one-off setup, transition, advisory, or project costs.',
                        'revenue_forecast_missing', 'no_break_even' => 'Update the revenue forecast and margin assumptions until break-even is visible or the risk is accepted.',
                        'missing_assumptions' => 'Complete growth, margin, inflation, and profit-target assumptions.',
                        'too_many_guesses' => 'Replace guessed rows with uploaded evidence, client confirmation, or advisor-reviewed estimates.',
                        'tax_not_configured' => 'Configure current company tax reference data before relying on after-tax outputs.',
                        'financial_snapshot_discrepancy' => 'Reconcile the budget forecast against the latest accounting snapshot before advisor approval.',
                        default => (string) ($flag['message'] ?? 'Review this budget signal before proposal reliance.'),
                    },
                    'reason' => (string) ($flag['message'] ?? ''),
                ];
            });

        if ((int) data_get($confidence, 'score', 0) < 55 && $budget->isUnlocked()) {
            $actions->push([
                'priority' => 'medium',
                'action' => 'Treat the budget as preliminary until evidence and assumptions improve.',
                'reason' => 'Budget confidence is below the developing threshold.',
            ]);
        }

        if ((float) data_get($computed, 'available_after_launch', 0) < 0) {
            $actions->push([
                'priority' => 'high',
                'action' => 'Confirm extra funding, delay implementation spend, or reduce launch costs.',
                'reason' => 'Available cash after launch costs is negative.',
            ]);
        }

        if ($actions->isEmpty()) {
            $actions->push([
                'priority' => 'low',
                'action' => 'Maintain the current budget and proceed to advisor review when the plan is complete.',
                'reason' => 'No active budget warnings are present.',
            ]);
        }

        return $actions->values()->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array{key:string,title:string,status:string,status_label:string,score:int,summary:string,evidence:array<int, string>}>
     */
    private function assessmentCriteria(StrategicBudget $budget, array $computed, array $confidence): array
    {
        $sections = collect((array) ($budget->business_plan_sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $sectionComplete = fn (string $key): bool => trim((string) data_get($sections->get($key), 'answer', '')) !== '';
        $sectionTitle = fn (string $key): string => (string) data_get(
            $sections->get($key),
            'title',
            str($key)->replace('_', ' ')->title()->toString(),
        );
        $completedSections = collect(self::PLAN_SECTION_KEYS)
            ->filter(fn (string $key): bool => $sectionComplete($key))
            ->count();
        $missingSections = collect(self::PLAN_SECTION_KEYS)
            ->reject(fn (string $key): bool => $sectionComplete($key))
            ->map(fn (string $key): string => $sectionTitle($key))
            ->values()
            ->all();

        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $sourceFinancialCount = (int) ($sourceFinancials['count'] ?? 0);
        $hasFinancials = (bool) ($sourceFinancials['unlocked'] ?? false);
        $sourceDraftCount = collect((array) ($budget->business_plan_source_drafts ?? []))
            ->filter(fn (mixed $draft): bool => is_array($draft)
                && (
                    trim((string) ($draft['body'] ?? '')) !== ''
                    || trim((string) ($draft['source_label'] ?? '')) !== ''
                ))
            ->count();
        $hasRows = fn (string $attribute): bool => collect((array) ($budget->{$attribute} ?? []))
            ->filter(fn (mixed $row): bool => is_array($row)
                && (
                    trim((string) ($row['label'] ?? '')) !== ''
                    || (is_numeric(data_get($row, 'amount')) && (float) data_get($row, 'amount', 0) > 0)
                ))
            ->isNotEmpty();
        $missingAssumptions = $this->missingAssumptions($computed);
        $hasBudgetInputs = $this->hasBudgetInputs($budget);
        $hasRevenueForecast = $hasRows('revenue_forecast');
        $hasImplementationCosts = $hasRows('implementation_costs');
        $hasMonthlyCosts = $hasRows('monthly_fixed_costs');
        $hasFunding = $hasRows('funding_sources') || $hasRows('funding_scenarios');
        $marketComplete = $sectionComplete('market_customers');
        $operationsComplete = $sectionComplete('operations');
        $evidenceComplete = $sectionComplete('evidence_documents');
        $riskSectionCount = collect(['risks', 'swot', 'action_priorities'])
            ->filter(fn (string $key): bool => $sectionComplete($key))
            ->count();
        $availableAfterLaunch = (float) data_get($computed, 'available_after_launch', 0);
        $runwayOpenEnded = (bool) data_get($computed, 'runway_open_ended', false);
        $runwayMonths = data_get($computed, 'runway_months');
        $expectedRunway = $budget->expected_runway_months;
        $runwayMeetsRequirement = $runwayOpenEnded
            || $expectedRunway === null
            || (is_numeric($runwayMonths) && (int) $runwayMonths >= (int) $expectedRunway);
        $breakEvenVisible = data_get($computed, 'break_even_year') !== null
            || (bool) data_get($computed, 'break_even_reached', false);
        $confidenceScore = (int) data_get($confidence, 'score', 0);
        $activeFlags = count((array) ($budget->flags ?? []));
        $reviewSubmitted = $this->reviewSubmittedOrLater($budget);
        $financialEvidenceScore = match (true) {
            ! $hasFinancials => 0,
            $sourceFinancialCount >= 2 => 100,
            default => 70,
        };
        $evidenceLinkageScore = min(
            100,
            ($evidenceComplete ? 50 : 0)
            + min(25, $sourceDraftCount * 10)
            + min(25, $sourceFinancialCount * 10),
        );
        $fundingScore = ($hasFunding ? 40 : 0)
            + ($availableAfterLaunch >= 0 ? 25 : 0)
            + ($runwayMeetsRequirement ? 20 : 0)
            + ($breakEvenVisible ? 15 : 0);

        return [
            $this->criterion(
                'plan_structure',
                'Plan structure and completeness',
                $completedSections === count(self::PLAN_SECTION_KEYS)
                    ? 'met'
                    : ($completedSections > 0 ? 'review' : 'missing'),
                $this->businessPlanReadiness($budget),
                $completedSections.'/'.count(self::PLAN_SECTION_KEYS).' DD BP&B plan sections are complete.',
                $missingSections === []
                    ? ['All required DD BP&B sections have client answers.']
                    : ['Missing sections: '.implode(', ', $missingSections).'.'],
            ),
            $this->criterion(
                'dd_evidence_linkage',
                $budget->pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
                    ? 'DD evidence linkage'
                    : 'Evidence linkage',
                $evidenceComplete && ($sourceDraftCount > 0 || $sourceFinancialCount > 0)
                    ? 'met'
                    : ($evidenceComplete || $sourceDraftCount > 0 || $sourceFinancialCount > 0 ? 'review' : 'missing'),
                $evidenceLinkageScore,
                $sourceDraftCount.' source draft(s) and '.$sourceFinancialCount.' verified financial evidence file(s) support the plan.',
                [
                    $evidenceComplete
                        ? 'Evidence/documents section is complete.'
                        : 'Evidence/documents section needs completion.',
                    $sourceFinancialCount > 0
                        ? $sourceFinancialCount.' verified financial upload(s) are linked.'
                        : 'No verified financial uploads are linked yet.',
                ],
            ),
            $this->criterion(
                'financial_evidence_quality',
                'Financial evidence quality',
                ! $hasFinancials
                    ? 'missing'
                    : ($sourceFinancialCount >= 2 ? 'met' : 'review'),
                $financialEvidenceScore,
                $hasFinancials
                    ? $sourceFinancialCount.' qualifying P&L or management-accounts upload(s) are available.'
                    : 'A verified P&L or management-accounts upload is required.',
                [
                    $sourceFinancialCount >= 2
                        ? 'Evidence base is stronger than the minimum unlock threshold.'
                        : 'One upload is enough to start, but another source file improves reliance.',
                ],
            ),
            $this->criterion(
                'forecast_assumptions',
                'Forecast assumptions',
                ! $hasBudgetInputs
                    ? 'missing'
                    : ($missingAssumptions === [] ? 'met' : 'review'),
                $hasBudgetInputs ? max(0, 100 - (count($missingAssumptions) * 20)) : 0,
                $missingAssumptions === []
                    ? 'No missing growth, margin, inflation, or profit-target assumptions are active.'
                    : count($missingAssumptions).' assumption(s) need detail.',
                $missingAssumptions === []
                    ? ['Budget assumptions are complete for the current forecast.']
                    : ['Missing assumptions: '.collect($missingAssumptions)->pluck('label')->implode(', ').'.'],
            ),
            $this->criterion(
                'revenue_customer_risk',
                'Revenue and customer risk',
                $hasRevenueForecast && $marketComplete
                    ? 'met'
                    : ($hasRevenueForecast || $marketComplete ? 'review' : 'missing'),
                ($hasRevenueForecast ? 50 : 0) + ($marketComplete ? 50 : 0),
                $hasRevenueForecast
                    ? 'Revenue forecast is present and can be checked against customer/market assumptions.'
                    : 'Revenue forecast is missing.',
                [
                    $marketComplete
                        ? 'Market/customers section is complete.'
                        : 'Market/customers section needs completion.',
                    $hasRevenueForecast
                        ? 'Revenue rows are present.'
                        : 'Revenue rows need to be added.',
                ],
            ),
            $this->criterion(
                'cost_integration_budget',
                'Cost and integration budget',
                $hasImplementationCosts && $hasMonthlyCosts && $operationsComplete
                    ? 'met'
                    : ($hasImplementationCosts || $hasMonthlyCosts || $operationsComplete ? 'review' : 'missing'),
                ($operationsComplete ? 34 : 0) + ($hasImplementationCosts ? 33 : 0) + ($hasMonthlyCosts ? 33 : 0),
                'Checks acquisition/setup costs, recurring costs, and the operating handover/integration narrative.',
                [
                    $operationsComplete ? 'Operations section is complete.' : 'Operations section needs completion.',
                    $hasImplementationCosts ? 'Implementation/setup cost rows are present.' : 'Implementation/setup cost rows are missing.',
                    $hasMonthlyCosts ? 'Monthly fixed cost rows are present.' : 'Monthly fixed cost rows are missing.',
                ],
            ),
            $this->criterion(
                'funding_runway_affordability',
                'Funding, runway, and affordability',
                ! $hasFunding
                    ? 'missing'
                    : ($availableAfterLaunch >= 0 && $runwayMeetsRequirement ? 'met' : 'review'),
                $fundingScore,
                $hasFunding
                    ? 'Funding has been entered; affordability depends on cash after setup, runway, and break-even visibility.'
                    : 'Funding sources are missing.',
                [
                    'Available after setup/acquisition costs: '.$this->money($availableAfterLaunch).'.',
                    $runwayOpenEnded
                        ? 'Runway is open ended.'
                        : 'Runway is '.(is_numeric($runwayMonths) ? (int) $runwayMonths.' months' : 'not yet visible').'.',
                    $breakEvenVisible ? 'Break-even timing is visible.' : 'Break-even timing is not yet visible.',
                ],
            ),
            $this->criterion(
                'risk_action_readiness',
                'Risk, SWOT, and first 100-day actions',
                $riskSectionCount === 3
                    ? 'met'
                    : ($riskSectionCount > 0 ? 'review' : 'missing'),
                (int) round(($riskSectionCount / 3) * 100),
                $riskSectionCount.'/3 risk and action sections are complete.',
                [
                    $sectionComplete('risks') ? 'Risks section is complete.' : 'Risks section needs completion.',
                    $sectionComplete('swot') ? 'SWOT section is complete.' : 'SWOT section needs completion.',
                    $sectionComplete('action_priorities') ? 'Action priorities are complete.' : 'Action priorities need completion.',
                ],
            ),
            $this->criterion(
                'advisor_funder_readiness',
                'Advisor and funder readiness',
                $confidenceScore >= 80 && $this->businessPlanReady($budget) && $reviewSubmitted && $hasFinancials
                    ? 'met'
                    : ($confidenceScore >= 55 || $reviewSubmitted ? 'review' : 'missing'),
                $confidenceScore,
                'Overall BP&B confidence is '.$confidenceScore.'/100 with '.$activeFlags.' active readiness flag(s).',
                [
                    $reviewSubmitted ? 'BP&B has been submitted for advisor review.' : 'BP&B still needs to be submitted for advisor review.',
                    $this->businessPlanReady($budget) ? 'Business plan is complete.' : 'Business plan still has incomplete sections.',
                    $hasFinancials ? 'Financial evidence is available.' : 'Financial evidence is not yet available.',
                ],
            ),
        ];
    }

    /**
     * @param  array<int, string>  $evidence
     * @return array{key:string,title:string,status:string,status_label:string,score:int,summary:string,evidence:array<int, string>}
     */
    private function criterion(string $key, string $title, string $status, int $score, string $summary, array $evidence): array
    {
        $safeStatus = in_array($status, ['met', 'review', 'missing'], true) ? $status : 'review';

        return [
            'key' => $key,
            'title' => $title,
            'status' => $safeStatus,
            'status_label' => match ($safeStatus) {
                'met' => 'Met',
                'missing' => 'Missing',
                default => 'Needs review',
            },
            'score' => max(0, min(100, $score)),
            'summary' => $summary,
            'evidence' => collect($evidence)
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function createAssessmentVersion(
        StrategicBudget $budget,
        User $actor,
        string $status,
    ): StrategicBudgetAssessment {
        $budget = $budget->refresh();
        $computed = $this->computedForRead($budget);
        $confidence = (array) ($budget->confidence ?? []);
        $criteria = $this->assessmentCriteria($budget, $computed, $confidence);

        return DB::transaction(function () use ($budget, $actor, $status, $computed, $confidence, $criteria): StrategicBudgetAssessment {
            StrategicBudget::query()
                ->whereKey($budget->getKey())
                ->lockForUpdate()
                ->first();

            $round = ((int) StrategicBudgetAssessment::query()
                ->where('strategic_budget_id', $budget->getKey())
                ->max('round')) + 1;

            $assessment = StrategicBudgetAssessment::query()->create([
                'strategic_budget_id' => $budget->getKey(),
                'client_id' => $budget->client_id,
                'round' => $round,
                'status' => $status,
                'snapshot' => $this->assessmentSnapshot($budget, $computed, $confidence, $criteria),
                'assessment_criteria' => $criteria,
                'scores' => $this->assessmentScores($budget, $confidence),
                'priorities' => [],
                'submitted_at' => $budget->business_plan_submitted_at ?? $budget->submitted_at ?? now(),
                'submitted_by_user_id' => $actor->getKey(),
            ]);

            $this->audit->record('strategic_budget.version_created', subject: $assessment, actor: $actor, after: [
                'strategic_budget_id' => $budget->getKey(),
                'client_id' => $budget->client_id,
                'version' => $assessment->round,
                'status' => $assessment->status,
            ]);

            return $assessment->refresh();
        });
    }

    private function recordAssessmentRun(StrategicBudget $budget, User $actor): StrategicBudgetAssessment
    {
        $budget = $budget->refresh();
        $computed = $this->computedForRead($budget);
        $confidence = (array) ($budget->confidence ?? []);
        $criteria = $this->assessmentCriteria($budget, $computed, $confidence);
        $priorities = $this->feedbackPriorities($criteria);
        $assessment = $this->latestAssessmentForCurrentSubmission($budget)
            ?? $this->createAssessmentVersion($budget, $actor, StrategicBudgetAssessment::STATUS_SUBMITTED);
        $suggestedFeedback = $this->suggestedAdvisorFeedback($budget, $criteria, $priorities);
        $suggestedReply = $this->suggestedClientReply($budget, $criteria, $priorities);

        $assessment->forceFill([
            'status' => StrategicBudgetAssessment::STATUS_ASSESSED,
            'snapshot' => $this->assessmentSnapshot($budget, $computed, $confidence, $criteria),
            'assessment_criteria' => $criteria,
            'scores' => $this->assessmentScores($budget, $confidence),
            'priorities' => $priorities,
            'suggested_feedback' => $suggestedFeedback,
            'suggested_reply' => $suggestedReply,
            'advisor_feedback' => $assessment->advisor_feedback ?: $suggestedFeedback,
            'proposed_reply' => $assessment->proposed_reply ?: $suggestedReply,
            'assessed_at' => now(),
            'assessed_by_user_id' => $actor->getKey(),
        ])->save();

        return $assessment->refresh();
    }

    private function markLatestAssessmentApproved(StrategicBudget $budget, User $actor): void
    {
        $assessment = $this->latestAssessmentForCurrentSubmission($budget)
            ?? $this->latestAssessment($budget);

        if (! $assessment instanceof StrategicBudgetAssessment) {
            return;
        }

        $assessment->forceFill([
            'status' => StrategicBudgetAssessment::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $actor->getKey(),
        ])->save();
    }

    private function latestAssessment(StrategicBudget $budget): ?StrategicBudgetAssessment
    {
        return StrategicBudgetAssessment::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->latest('round')
            ->first();
    }

    private function latestAssessmentForCurrentSubmission(StrategicBudget $budget): ?StrategicBudgetAssessment
    {
        $submittedAt = $budget->business_plan_submitted_at ?? $budget->submitted_at;

        if ($submittedAt === null) {
            return $this->latestAssessment($budget);
        }

        return StrategicBudgetAssessment::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->where('submitted_at', $submittedAt)
            ->latest('round')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function assessmentSnapshot(
        StrategicBudget $budget,
        array $computed,
        array $confidence,
        array $criteria,
    ): array {
        return [
            'captured_at' => now()->toIso8601String(),
            'strategic_budget_id' => $budget->getKey(),
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'horizon_months' => $budget->horizon_months,
            'expected_runway_months' => $budget->expected_runway_months,
            'business_plan_sections' => $budget->business_plan_sections ?? [],
            'business_plan_source_drafts' => $budget->business_plan_source_drafts ?? [],
            'business_plan_prompts' => $budget->business_plan_prompts ?? [],
            'source_financials' => $budget->source_financials ?? [],
            'assumptions' => $budget->assumptions ?? [],
            'implementation_costs' => $budget->implementation_costs ?? [],
            'monthly_fixed_costs' => $budget->monthly_fixed_costs ?? [],
            'future_costs' => $budget->future_costs ?? [],
            'revenue_forecast' => $budget->revenue_forecast ?? [],
            'funding_sources' => $budget->funding_sources ?? [],
            'funding_scenarios' => $budget->funding_scenarios ?? [],
            'computed' => $computed,
            'flags' => $budget->flags ?? [],
            'confidence' => $confidence,
            'assessment_criteria' => $criteria,
        ];
    }

    /**
     * @param  array<string, mixed>  $confidence
     * @return array<string, int>
     */
    private function assessmentScores(StrategicBudget $budget, array $confidence): array
    {
        return [
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
            'progress' => (int) data_get($confidence, 'progress_score', 0),
            'readiness' => (int) data_get($confidence, 'score', 0),
            'confidence' => (int) data_get($confidence, 'score', 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array<string, mixed>>
     */
    private function feedbackPriorities(array $criteria): array
    {
        $statusRank = [
            'missing' => 0,
            'review' => 1,
            'met' => 2,
        ];

        return collect($criteria)
            ->filter(fn (mixed $criterion): bool => is_array($criterion))
            ->sortBy(fn (array $criterion): string => sprintf(
                '%d-%03d-%s',
                $statusRank[(string) ($criterion['status'] ?? 'review')] ?? 1,
                (int) ($criterion['score'] ?? 0),
                (string) ($criterion['title'] ?? ''),
            ))
            ->take(3)
            ->values()
            ->map(fn (array $criterion, int $index): array => [
                'rank' => $index + 1,
                'key' => (string) ($criterion['key'] ?? ''),
                'title' => (string) ($criterion['title'] ?? 'Assessment priority'),
                'score' => (int) ($criterion['score'] ?? 0),
                'status' => (string) ($criterion['status'] ?? 'review'),
                'status_label' => (string) ($criterion['status_label'] ?? 'Needs review'),
                'summary' => (string) ($criterion['summary'] ?? ''),
                'evidence' => array_values((array) ($criterion['evidence'] ?? [])),
                'suggested_next_step' => $this->priorityNextStep((string) ($criterion['key'] ?? ''), (string) ($criterion['status'] ?? 'review')),
            ])
            ->all();
    }

    private function priorityNextStep(string $key, string $status): string
    {
        $prefix = $status === 'met'
            ? 'Keep this evidence current: '
            : 'Ask the client to strengthen this area: ';

        return $prefix.match ($key) {
            'plan_structure' => 'complete any missing BP&B sections so the advisor can review the whole funding story.',
            'dd_evidence_linkage' => 'link the DD findings, verified evidence, and source documents that support the plan.',
            'financial_evidence_quality' => 'upload or confirm additional P&L or management accounts evidence before relying on the budget.',
            'forecast_assumptions' => 'explain the growth, margin, inflation, timing, and profit assumptions behind the forecast.',
            'revenue_customer_risk' => 'show how revenue is supported by customer evidence, market position, retention, and concentration risk.',
            'cost_integration_budget' => 'confirm setup, handover, recurring, and integration costs with owner and timing detail.',
            'funding_runway_affordability' => 'show the funding source, available cash after setup, runway position, and affordability buffer.',
            'risk_action_readiness' => 'turn the DD risks and SWOT into first 100-day actions with clear priorities.',
            'advisor_funder_readiness' => 'make the plan reliable enough for advisor approval and funding conversations.',
            default => 'add the missing evidence and plain-English explanation for this assessment item.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<int, array<string, mixed>>  $priorities
     */
    private function suggestedAdvisorFeedback(StrategicBudget $budget, array $criteria, array $priorities): string
    {
        $confidenceScore = (int) data_get($budget->confidence, 'score', 0);
        $businessPlanScore = $this->businessPlanReadiness($budget);
        $reviewCount = collect($criteria)
            ->filter(fn (array $criterion): bool => ($criterion['status'] ?? null) !== 'met')
            ->count();
        $intro = sprintf(
            'BP&B assessment completed: readiness is %d/100 and business-plan completeness is %d/100.',
            $confidenceScore,
            $businessPlanScore,
        );

        if ($priorities === [] || $reviewCount === 0) {
            return $intro."\n\nThe current DD-sourced Business Plan & Budget is ready for advisor approval, subject to final professional judgement and any required funding-provider checks.";
        }

        return implode("\n\n", [
            $intro,
            sprintf('%d assessment area(s) still need advisor judgement or client strengthening before approval.', $reviewCount),
            'Suggested feedback priorities:',
            $this->formatFeedbackPriorities($priorities, includeScores: true),
            'Use the suggested client reply as a starting point; edit it before sending if commercial wording or funding context needs nuance.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<int, array<string, mixed>>  $priorities
     */
    private function suggestedClientReply(StrategicBudget $budget, array $criteria, array $priorities): string
    {
        $clientName = $this->clientDisplayName($budget);
        $confidenceScore = (int) data_get($budget->confidence, 'score', 0);
        $openPriorities = collect($priorities)
            ->filter(fn (array $priority): bool => ($priority['status'] ?? null) !== 'met')
            ->values()
            ->all();

        if ($openPriorities === []) {
            return implode("\n\n", [
                "Hi {$clientName},",
                "I have reviewed the DD-sourced Business Plan & Budget. The current assessment is {$confidenceScore}/100, and the plan is ready for advisor approval subject to final checks.",
                'I will confirm the approval position and let you know if any funding-provider wording needs to be tightened before you use the plan and budget for funding discussions.',
            ]);
        }

        return implode("\n\n", [
            "Hi {$clientName},",
            'I have reviewed the DD-sourced Business Plan & Budget. You do not need to start again; the useful next step is to strengthen the few areas that will matter most for approval and funding conversations.',
            $this->formatFeedbackPriorities($openPriorities, includeScores: false),
            'Once you have updated those points, please resubmit the Business Plan & Budget and I will run the next assessment version.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $priorities
     */
    private function formatFeedbackPriorities(array $priorities, bool $includeScores): string
    {
        return collect($priorities)
            ->values()
            ->map(function (array $priority, int $index) use ($includeScores): string {
                $heading = sprintf('%d. %s', $index + 1, (string) ($priority['title'] ?? 'Assessment priority'));
                if ($includeScores) {
                    $heading .= sprintf(' (%d/100, %s)', (int) ($priority['score'] ?? 0), (string) ($priority['status_label'] ?? 'Needs review'));
                }

                return implode("\n", array_values(array_filter([
                    $heading,
                    (string) ($priority['summary'] ?? ''),
                    'Suggested next step: '.(string) ($priority['suggested_next_step'] ?? 'Strengthen this area before approval.'),
                ])));
            })
            ->implode("\n\n");
    }

    private function clientDisplayName(StrategicBudget $budget): string
    {
        $client = $budget->relationLoaded('client')
            ? $budget->client
            : $budget->client()->first();

        if ($client instanceof Client) {
            return trim((string) ($client->trading_name ?: $client->legal_name)) ?: 'there';
        }

        return 'there';
    }

    /**
     * @param  array{suggested_feedback:string,suggested_reply:string}  $suggestions
     * @return array<string, mixed>
     */
    private function feedbackSnapshotWithEdits(
        StrategicBudgetAssessment $assessment,
        array $suggestions,
        string $advisorFeedback,
        string $proposedReply,
        bool $sentToClient,
        User $actor,
    ): array {
        $advisorFeedback = trim($advisorFeedback);
        $proposedReply = trim($proposedReply);
        $suggestedFeedback = trim($suggestions['suggested_feedback']);
        $suggestedReply = trim($suggestions['suggested_reply']);

        return [
            'saved_at' => now()->toIso8601String(),
            'saved_by_user_id' => $actor->getKey(),
            'sent_to_client' => $sentToClient,
            'source' => [
                'strategic_budget_assessment_id' => $assessment->getKey(),
                'strategic_budget_id' => $assessment->strategic_budget_id,
                'client_id' => $assessment->client_id,
                'version' => $assessment->round,
            ],
            'suggested_feedback' => [
                'sha256' => $this->textHash($suggestedFeedback),
                'length' => Str::length($suggestedFeedback),
            ],
            'suggested_reply' => [
                'sha256' => $this->textHash($suggestedReply),
                'length' => Str::length($suggestedReply),
            ],
            'advisor_edits' => [
                'feedback_sha256' => $this->textHash($advisorFeedback),
                'proposed_reply_sha256' => $this->textHash($proposedReply),
                'feedback_changed_from_suggestion' => $this->textHash($advisorFeedback) !== $this->textHash($suggestedFeedback),
                'proposed_reply_changed_from_suggestion' => $this->textHash($proposedReply) !== $this->textHash($suggestedReply),
                'feedback_length_delta' => Str::length($advisorFeedback) - Str::length($suggestedFeedback),
                'proposed_reply_length_delta' => Str::length($proposedReply) - Str::length($suggestedReply),
            ],
        ];
    }

    private function textHash(string $text): string
    {
        return hash('sha256', Str::squish($text));
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentFeedbackPayload(StrategicBudget $budget): array
    {
        $assessment = $this->latestAssessmentForCurrentSubmission($budget)
            ?? $this->latestAssessment($budget);

        $canSave = $assessment instanceof StrategicBudgetAssessment
            && $assessment->assessed_at !== null;
        $status = $assessment instanceof StrategicBudgetAssessment
            ? (string) $assessment->status
            : 'not_started';
        $advisorFeedback = $assessment instanceof StrategicBudgetAssessment
            ? (string) ($assessment->advisor_feedback ?? $assessment->suggested_feedback ?? '')
            : '';
        $proposedReply = $assessment instanceof StrategicBudgetAssessment
            ? (string) ($assessment->proposed_reply ?? $assessment->suggested_reply ?? '')
            : '';
        $suggestedFeedback = $assessment instanceof StrategicBudgetAssessment
            ? (string) ($assessment->suggested_feedback ?? '')
            : '';
        $suggestedReply = $assessment instanceof StrategicBudgetAssessment
            ? (string) ($assessment->suggested_reply ?? '')
            : '';

        return [
            'id' => $assessment?->getKey(),
            'version' => $assessment?->round,
            'status' => $status,
            'status_label' => $assessment instanceof StrategicBudgetAssessment
                ? $this->assessmentVersionStatusLabel($status)
                : 'Run assessment first',
            'advisor_feedback' => $advisorFeedback,
            'proposed_reply' => $proposedReply,
            'suggested_feedback' => $suggestedFeedback,
            'suggested_reply' => $suggestedReply,
            'priorities' => $assessment instanceof StrategicBudgetAssessment
                ? (array) ($assessment->priorities ?? [])
                : [],
            'sent_at' => $assessment?->feedback_sent_at?->toIso8601String(),
            'saved_at' => $assessment?->feedback_saved_at?->toIso8601String(),
            'can_save' => $canSave,
            'can_send' => $canSave,
            'action_url' => route('advisor.clients.strategic-budget.feedback', $budget->client_id, absolute: false),
            'message_url' => $assessment?->client_message_thread_id
                ? route('advisor.clients.messages.show', [$budget->client_id, $assessment->client_message_thread_id], absolute: false)
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assessmentHistoryPayload(StrategicBudget $budget): array
    {
        $previousScore = null;

        return StrategicBudgetAssessment::query()
            ->where('strategic_budget_id', $budget->getKey())
            ->orderBy('round')
            ->get()
            ->map(function (StrategicBudgetAssessment $assessment) use (&$previousScore): array {
                $scores = (array) ($assessment->scores ?? []);
                $snapshot = (array) ($assessment->snapshot ?? []);
                $readiness = is_numeric($scores['readiness'] ?? null) ? (int) $scores['readiness'] : null;
                $scoreDelta = $readiness === null || $previousScore === null
                    ? null
                    : $readiness - $previousScore;
                if ($readiness !== null) {
                    $previousScore = $readiness;
                }

                return [
                    'id' => $assessment->getKey(),
                    'version' => $assessment->round,
                    'status' => (string) $assessment->status,
                    'status_label' => $this->assessmentVersionStatusLabel((string) $assessment->status),
                    'submitted_at' => $assessment->submitted_at?->toIso8601String(),
                    'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                    'feedback_sent_at' => $assessment->feedback_sent_at?->toIso8601String(),
                    'approved_at' => $assessment->approved_at?->toIso8601String(),
                    'readiness_score' => $readiness,
                    'business_plan_score' => is_numeric($scores['business_plan_readiness'] ?? null) ? (int) $scores['business_plan_readiness'] : null,
                    'budget_confidence_score' => is_numeric($scores['confidence'] ?? null) ? (int) $scores['confidence'] : null,
                    'score_delta' => $scoreDelta,
                    'priorities' => collect((array) ($assessment->priorities ?? []))
                        ->take(3)
                        ->values()
                        ->all(),
                    'suggested_reply_excerpt' => Str::limit((string) ($assessment->proposed_reply ?? $assessment->suggested_reply ?? ''), 180),
                    'message_url' => $assessment->client_message_thread_id
                        ? route('advisor.clients.messages.show', [$assessment->client_id, $assessment->client_message_thread_id], absolute: false)
                        : null,
                    'snapshot_available' => $snapshot !== [],
                    'snapshot_captured_at' => data_get($snapshot, 'captured_at'),
                ];
            })
            ->sortByDesc('version')
            ->values()
            ->all();
    }

    private function assessmentVersionStatusLabel(string $status): string
    {
        return match ($status) {
            StrategicBudgetAssessment::STATUS_SUBMITTED => 'Submitted',
            StrategicBudgetAssessment::STATUS_ASSESSED => 'Assessed',
            StrategicBudgetAssessment::STATUS_FEEDBACK_SAVED => 'Feedback saved',
            StrategicBudgetAssessment::STATUS_FEEDBACK_SENT => 'Feedback sent',
            StrategicBudgetAssessment::STATUS_APPROVED => 'Approved',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function plural(string $word, int $count): string
    {
        return $count === 1 ? $word : $word.'s';
    }

    private function refreshReadiness(StrategicBudget $budget): StrategicBudget
    {
        $confidence = $this->confidence($budget, (array) ($budget->computed ?? []));
        $flags = $this->flags($budget, (array) ($budget->computed ?? []), $confidence);

        $budget->forceFill([
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return Collection<int, Document>
     */
    private function financialDocuments(Client $client): Collection
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->with('verifications')
            ->latest()
            ->get()
            ->filter(fn (Document $document): bool => $this->isBudgetFinancialDocument($document)
                && $this->hasVerifiedFinancialEvidence($document))
            ->values();
    }

    private function isBudgetFinancialDocument(Document $document): bool
    {
        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return true;
        }

        $filename = str((string) $document->original_filename)->lower()->toString();

        foreach (self::FINANCIAL_KEYWORDS as $keyword) {
            if (str_contains($filename, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function hasVerifiedFinancialEvidence(Document $document): bool
    {
        if ($document->verifications->isEmpty()) {
            return false;
        }

        return $document->verifications->every(
            fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
        );
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, mixed>
     */
    private function sourceFinancialsPayload(Collection $documents): array
    {
        $items = $documents
            ->take(8)
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'detected_as' => $this->detectedFinancialType($document),
                'verification_status' => 'verified',
            ])
            ->values()
            ->all();

        return [
            'unlocked' => $documents->isNotEmpty(),
            'count' => $documents->count(),
            'items' => $items,
            'required_tags' => ['P&L', 'Management Accounts'],
            'system_review' => $documents->isNotEmpty()
                ? 'Verified financial upload is suitable as a starting point.'
                : 'Upload and verify a P&L or management accounts file to unlock the budget.',
        ];
    }

    private function detectedFinancialType(Document $document): string
    {
        $filename = str((string) $document->original_filename)->lower()->toString();

        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return 'Financial statement';
        }

        if (str_contains($filename, 'management')) {
            return 'Management accounts';
        }

        if (str_contains($filename, 'p&l') || str_contains($filename, 'profit')) {
            return 'P&L';
        }

        return 'Financial upload';
    }

    /**
     * @return array<int, array{title:string,measure:string,owner:string,locked:bool}>
     */
    private function clientGoals(Client $client): array
    {
        $state = is_array($client->onboarding_wizard_state) ? $client->onboarding_wizard_state : [];
        $goals = (array) data_get($state, 'steps.goals', []);
        $primary = trim((string) ($goals['primary_goal'] ?? ''));
        $measure = trim((string) ($goals['success_measure'] ?? ''));

        if ($primary === '' && $measure === '') {
            return [];
        }

        return [[
            'title' => $primary !== '' ? $primary : 'Client onboarding goal',
            'measure' => $measure,
            'owner' => 'client',
            'locked' => false,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{key:string,title:string,prompt:string,answer:string}>
     */
    private function normaliseBusinessPlanSections(array $sections, string $pathway): array
    {
        $byKey = collect($sections)
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(function (string $key) use ($byKey, $prompts): array {
                $prompt = (array) ($prompts->get($key) ?? []);
                $section = (array) ($byKey->get($key) ?? []);

                return [
                    'key' => $key,
                    'title' => (string) ($prompt['title'] ?? str($key)->replace('_', ' ')->title()->toString()),
                    'prompt' => (string) ($prompt['prompt'] ?? ''),
                    'answer' => trim((string) ($section['answer'] ?? $section['body'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,prompt:string}>
     */
    private function businessPlanPrompts(string $pathway): array
    {
        $variant = match ($pathway) {
            StrategicBudget::PATHWAY_DUE_DILIGENCE => 'due_diligence',
            StrategicBudget::PATHWAY_NPO => 'npo',
            default => 'advisory',
        };

        $prompts = [
            'advisory' => [
                'goals' => 'Confirm the practical business outcomes this advisory work must support.',
                'current_position' => 'Describe the current operating, financial, and leadership position.',
                'market_customers' => 'Summarise core customers, market position, demand signals, and customer risks.',
                'operations' => 'Explain the operating model, systems, people, capacity, and delivery constraints.',
                'risks' => 'Identify the most important commercial, financial, compliance, people, and execution risks.',
                'swot' => 'Summarise strengths, weaknesses, opportunities, and threats in plain language.',
                'action_priorities' => 'Set the near-term actions that would make the proposal more likely to succeed.',
                'evidence_documents' => 'List the documents, numbers, and evidence that support this plan.',
            ],
            'due_diligence' => [
                'goals' => 'Confirm the acquisition goal, target outcome, and what must be true after settlement.',
                'current_position' => 'Describe the buyer, target, DD status, and acquisition context.',
                'market_customers' => 'Summarise target customers, market position, concentration risk, and demand assumptions.',
                'operations' => 'Explain target operations, handover requirements, systems, people, and integration constraints.',
                'risks' => 'Identify acquisition, valuation, funding, integration, and post-settlement risks.',
                'swot' => 'Summarise the acquisition strengths, weaknesses, opportunities, and threats.',
                'action_priorities' => 'Set the first decision gates, completion actions, and first 100-day priorities.',
                'evidence_documents' => 'List DD evidence, financial uploads, workstream findings, and valuation sources.',
            ],
            'npo' => [
                'goals' => 'Confirm mission, operating, funding, governance, and impact outcomes.',
                'current_position' => 'Describe the current governance, service, funding, operational, and compliance position.',
                'market_customers' => 'Summarise beneficiaries, funders, communities, partners, and demand for services.',
                'operations' => 'Explain programmes, volunteers/staff, delivery capacity, systems, and reporting rhythm.',
                'risks' => 'Identify funding, governance, compliance, service-delivery, and reputation risks.',
                'swot' => 'Summarise mission strengths, capability gaps, opportunities, and threats.',
                'action_priorities' => 'Set practical operating priorities that improve sustainability and impact.',
                'evidence_documents' => 'List funding records, budgets, governance documents, impact evidence, and financial uploads.',
            ],
        ];

        $titles = [
            'goals' => 'Goals',
            'current_position' => 'Current position',
            'market_customers' => 'Market / customers',
            'operations' => 'Operations',
            'risks' => 'Risks',
            'swot' => 'SWOT',
            'action_priorities' => 'Action priorities',
            'evidence_documents' => 'Evidence / documents',
        ];

        return collect(self::PLAN_SECTION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => $titles[$key],
                'prompt' => $prompts[$variant][$key],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,source_label:string,source_url:string,source_help:string,body:string}>
     */
    private function sourceDrafts(Client $client, ?BusinessPlan $plan, string $pathway): array
    {
        $clientGoals = $this->clientGoals($client);
        $goalDraft = collect($clientGoals)
            ->map(fn (array $goal): string => trim($goal['title'].' '.($goal['measure'] ?? '')))
            ->filter()
            ->implode("\n");
        $ddDraft = $this->ddSourceDraft($plan);
        $documentCount = Document::query()
            ->where('client_id', $client->getKey())
            ->count();

        $drafts = [
            'goals' => $goalDraft,
            'current_position' => $ddDraft !== ''
                ? $ddDraft
                : trim(($client->trading_name ?: $client->legal_name).' is in the '.$this->engagementLabel($client).' pathway.'),
            'market_customers' => '',
            'operations' => '',
            'risks' => '',
            'swot' => '',
            'action_priorities' => '',
            'evidence_documents' => $documentCount > 0
                ? "{$documentCount} document(s) are available as plan evidence. Confirm which documents support each section."
                : 'No supporting evidence has been attached to this plan yet.',
        ];

        if ($pathway !== StrategicBudget::PATHWAY_DUE_DILIGENCE) {
            $drafts = array_replace(
                $drafts,
                $this->onboardingSourceDrafts($client, $goalDraft, $documentCount),
            );
        }

        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(function (string $key) use ($drafts, $pathway, $prompts): array {
                $source = $this->sourceDraftLink($pathway, $key);

                return [
                    'key' => $key,
                    'title' => (string) data_get($prompts->get($key), 'title', str($key)->replace('_', ' ')->title()->toString()),
                    'source_label' => $source['label'],
                    'source_url' => $source['url'],
                    'source_help' => $source['help'],
                    'body' => trim((string) ($drafts[$key] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{label:string,url:string,help:string}
     */
    private function sourceDraftLink(string $pathway, string $key): array
    {
        if ($pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE) {
            return [
                'label' => 'Source draft from Due Diligence',
                'url' => route('portal.dd-plan.show', absolute: false),
                'help' => 'Open the due diligence workspace used to populate this source draft.',
            ];
        }

        $step = match ($key) {
            'goals' => 'goals',
            'evidence_documents' => 'documents',
            default => 'questionnaire',
        };

        return [
            'label' => match ($key) {
                'goals' => 'Source goals from onboarding',
                'evidence_documents' => 'Source evidence documents',
                default => 'Source questionnaire answers',
            },
            'url' => route('portal.onboarding.step', ['step' => $step], absolute: false),
            'help' => match ($key) {
                'goals' => 'Open the onboarding goals used as source material for this section.',
                'evidence_documents' => 'Open onboarding documents and uploaded evidence used as source material for this section.',
                default => 'Open the onboarding questionnaire answers used as source material for this section.',
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function onboardingSourceDrafts(Client $client, string $goalDraft, int $documentCount): array
    {
        $state = is_array($client->onboarding_wizard_state) ? $client->onboarding_wizard_state : [];
        $questionnaireLines = $this->questionnaireSourceLines($client);
        $websiteLines = $this->websiteSourceLines($state);

        $drafts = [];
        foreach (self::PLAN_SECTION_KEYS as $key) {
            $lines = (array) ($questionnaireLines[$key] ?? []);

            if ($key === 'goals' && $goalDraft !== '') {
                array_unshift($lines, $goalDraft);
            }

            if ($key === 'current_position') {
                $lines = array_merge($websiteLines, $lines);
            }

            if ($key === 'evidence_documents' && $documentCount > 0) {
                array_unshift(
                    $lines,
                    "{$documentCount} document(s) are available as plan evidence. Confirm which documents support each section.",
                );
            }

            $drafts[$key] = $this->sourceLinesToText($lines);
        }

        if ($drafts['evidence_documents'] === '') {
            $drafts['evidence_documents'] = 'No supporting evidence has been attached to this plan yet.';
        }

        return $drafts;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    private function websiteSourceLines(array $state): array
    {
        $websiteUrl = trim((string) data_get($state, 'steps.website.website_url', ''));
        $websiteSkipped = (bool) data_get($state, 'steps.website.website_skipped', false);

        if ($websiteUrl !== '') {
            return ['Website supplied during onboarding: '.$websiteUrl];
        }

        if ($websiteSkipped) {
            return ['Website supplied during onboarding: client confirmed the business does not have a public website.'];
        }

        return [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function questionnaireSourceLines(Client $client): array
    {
        $lines = collect(self::PLAN_SECTION_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => []])
            ->all();
        $response = $this->latestQuestionnaireResponse($client);

        if (! $response instanceof QuestionnaireResponse) {
            return $lines;
        }

        $response->answers->each(function (QuestionnaireAnswer $answer) use (&$lines): void {
            $question = $answer->question;
            if ($question === null) {
                return;
            }

            $answerText = $this->answerValueToText($answer->value);
            $prompt = trim((string) $question->prompt);

            if ($answerText !== '') {
                $key = $this->planSectionKeyForAnswer($answer);
                $lines[$key][] = $prompt !== '' ? "{$prompt}: {$answerText}" : $answerText;
            }

            $attachedCount = count((array) ($answer->attached_document_ids ?? []));
            if ($attachedCount > 0) {
                $lines['evidence_documents'][] = $prompt !== ''
                    ? "{$prompt}: {$attachedCount} attached document(s)."
                    : "{$attachedCount} attached document(s).";
            }
        });

        return $lines;
    }

    private function latestQuestionnaireResponse(Client $client): ?QuestionnaireResponse
    {
        $set = $this->questionnaireSetForClient($client);

        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->where('set', $set->value))
            ->with(['answers.question.section'])
            ->latest('submitted_at')
            ->latest('updated_at')
            ->first();
    }

    private function questionnaireSetForClient(Client $client): QuestionnaireSet
    {
        return match ($client->engagement_type) {
            EngagementType::DUE_DILIGENCE => QuestionnaireSet::DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => QuestionnaireSet::POST_ACQUISITION_GAP,
            EngagementType::NPO => QuestionnaireSet::STANDARD_NPO,
            default => QuestionnaireSet::STANDARD_ADVISORY,
        };
    }

    private function planSectionKeyForAnswer(QuestionnaireAnswer $answer): string
    {
        $question = $answer->question;
        $source = str(implode(' ', [
            (string) data_get($question, 'section.title', ''),
            (string) ($question->prompt ?? ''),
        ]))->lower()->toString();

        if (Str::contains($source, ['swot', 'strength', 'weakness', 'opportunit', 'threat'])) {
            return 'swot';
        }

        if (Str::contains($source, ['document', 'evidence', 'upload', 'file attach', 'management account', 'financial statement', 'migrated dd document set'])) {
            return 'evidence_documents';
        }

        if (Str::contains($source, ['risk', 'red flag', 'legal', 'tax', 'compliance', 'insurance', 'dispute', 'dependency', 'concentration', 'not covered by dd'])) {
            return 'risks';
        }

        if (Str::contains($source, ['goal', 'objective', 'outcome', 'success measure', 'purpose'])) {
            return 'goals';
        }

        if (Str::contains($source, ['priority', 'action', 'next step', 'first 100', 'settlement', 'post-close', 'fix first', 'improve first'])) {
            return 'action_priorities';
        }

        if (Str::contains($source, ['customer', 'market', 'sales', 'demand', 'competitor', 'channel', 'supplier', 'product', 'service', 'revenue'])) {
            return 'market_customers';
        }

        if (Str::contains($source, ['operation', 'system', 'staff', 'people', 'hr', 'capacity', 'process', 'owner', 'leadership', 'handover', 'delivery'])) {
            return 'operations';
        }

        return 'current_position';
    }

    private function answerValueToText(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['value', 'label', 'text'] as $key) {
            if (array_key_exists($key, $value)) {
                return $this->answerValueToText($value[$key]);
            }
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $itemText = $this->answerValueToText($item);
            if ($itemText === '') {
                continue;
            }

            $parts[] = is_string($key) && ! is_numeric($key)
                ? str($key)->replace('_', ' ')->title()->toString().': '.$itemText
                : $itemText;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function sourceLinesToText(array $lines): string
    {
        return collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->take(8)
            ->implode("\n");
    }

    private function ddSourceDraft(?BusinessPlan $plan): string
    {
        if (! $plan instanceof BusinessPlan) {
            return '';
        }

        return $plan->sections()
            ->latest('updated_at')
            ->take(5)
            ->get(['title', 'body'])
            ->map(fn ($section): string => trim($section->title.': '.$section->body))
            ->filter()
            ->implode("\n\n");
    }

    private function engagementLabel(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType?->label() ?? str((string) $client->engagement_type)->replace('_', ' ')->title()->toString();
    }

    private function businessPlanReadiness(StrategicBudget $budget): int
    {
        $sections = (array) ($budget->business_plan_sections ?? []);
        if ($sections === []) {
            return 0;
        }

        $completed = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section) && trim((string) ($section['answer'] ?? '')) !== '')
            ->count();

        return (int) round(($completed / count(self::PLAN_SECTION_KEYS)) * 100);
    }

    private function businessPlanReady(StrategicBudget $budget): bool
    {
        return $this->businessPlanReadiness($budget) >= 100;
    }

    private function reviewSubmittedOrLater(StrategicBudget $budget): bool
    {
        return $this->reviewApprovedOrLater($budget)
            || $budget->submitted_at !== null
            || $budget->business_plan_submitted_at !== null
            || $budget->status === StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW;
    }

    private function reviewApprovedOrLater(StrategicBudget $budget): bool
    {
        return $budget->approved_at !== null
            || $budget->business_plan_approved_at !== null
            || $budget->isApprovedForProposal();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<string, mixed>
     */
    private function confidence(StrategicBudget $budget, array $computed): array
    {
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $hasFinancials = (bool) ($sourceFinancials['unlocked'] ?? false);
        $inputCount = (int) ($computed['input_count'] ?? 0);
        $missingAssumptions = (array) ($computed['missing_assumptions'] ?? []);
        $rowConfidence = $this->rowConfidence(
            (array) ($budget->implementation_costs ?? []),
            (array) ($budget->monthly_fixed_costs ?? []),
            (array) ($budget->future_costs ?? []),
            (array) ($budget->revenue_forecast ?? []),
            (array) ($budget->funding_sources ?? []),
            (array) ($budget->funding_scenarios ?? []),
        );

        $sourceScore = $hasFinancials ? 30 : 0;
        $inputScore = min(30, $inputCount * 4);
        $assumptionScore = max(0, 20 - (count($missingAssumptions) * 4));
        $rowScore = (int) round((float) ($rowConfidence['confidence_ratio'] ?? 0) * 20);
        $score = max(0, min(100, $sourceScore + $inputScore + $assumptionScore + $rowScore));

        return [
            'score' => $score,
            'progress_score' => $this->progressScore($budget, $computed, $hasFinancials),
            'source_score' => $sourceScore,
            'input_score' => $inputScore,
            'assumption_score' => $assumptionScore,
            'row_confidence_score' => $rowScore,
            'row_confidence' => $rowConfidence,
            'overall' => match (true) {
                $score >= 80 => 'strong',
                $score >= 55 => 'developing',
                $score > 0 => 'preliminary',
                default => 'locked',
            },
            'message' => $this->confidenceMessage($score, $hasFinancials),
        ];
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function progressScore(StrategicBudget $budget, array $computed, bool $hasFinancials): int
    {
        $steps = [
            $this->businessPlanReady($budget),
            $hasFinancials,
            ((array) ($budget->implementation_costs ?? [])) !== [],
            ((array) ($budget->monthly_fixed_costs ?? [])) !== [],
            ((array) ($budget->revenue_forecast ?? [])) !== [],
            ((array) ($budget->funding_sources ?? [])) !== [],
            $budget->expected_runway_months !== null,
            ((array) ($computed['missing_assumptions'] ?? [])) === [],
            in_array($budget->status, [
                StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true),
            $budget->isApprovedForProposal(),
        ];

        return (int) round((count(array_filter($steps)) / count($steps)) * 100);
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array{known:int,estimate:int,guess:int,total:int,guess_ratio:float,confidence_ratio:float}
     */
    private function rowConfidence(array ...$groups): array
    {
        $summary = ['known' => 0, 'estimate' => 0, 'guess' => 0, 'total' => 0, 'guess_ratio' => 0.0, 'confidence_ratio' => 0.0];

        foreach ($groups as $group) {
            foreach ($group as $row) {
                $confidence = in_array($row['confidence'] ?? '', ['known', 'estimate', 'guess'], true)
                    ? (string) $row['confidence']
                    : 'estimate';
                $summary[$confidence]++;
                $summary['total']++;
            }
        }

        if ($summary['total'] > 0) {
            $summary['guess_ratio'] = round($summary['guess'] / $summary['total'], 4);
            $weighted = ($summary['known'] * 1) + ($summary['estimate'] * 0.65) + ($summary['guess'] * 0.2);
            $summary['confidence_ratio'] = round($weighted / $summary['total'], 4);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array<string, string>>
     */
    private function flags(StrategicBudget $budget, array $computed, array $confidence): array
    {
        $flags = [];
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $planLabel = $budget->pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan'
            : 'Business Plan';

        if (! (bool) ($sourceFinancials['unlocked'] ?? false)) {
            $flags[] = $this->flag('financial_upload_required', 'Verify financials', 'Upload and verify a P&L or management accounts file before the budget can be edited.', 'high');
        } elseif ((int) ($sourceFinancials['count'] ?? 0) < 2) {
            $flags[] = $this->flag('partial_financials', 'Preliminary financial base', 'Only one qualifying financial upload is present. The budget can start, but more source files will improve the business plan and proposal.', 'medium');
        }

        if (! $this->businessPlanReady($budget)) {
            $flags[] = $this->flag('business_plan_incomplete', "{$planLabel} needs completion", "Complete every {$planLabel} section before submitting the combined plan and budget for advisor approval.", 'medium');
        }

        if (((array) ($budget->implementation_costs ?? [])) === []) {
            $flags[] = $this->flag('implementation_costs_missing', 'Implementation costs needed', 'Add the one-off setup, transition, advisory, or project costs that the plan needs to fund.', 'medium');
        }

        if (((array) ($budget->revenue_forecast ?? [])) === []) {
            $flags[] = $this->flag('revenue_forecast_missing', 'Revenue forecast needed', 'Add the expected revenue lines so affordability and proposal timing can be assessed.', 'medium');
        }

        if ((array) ($computed['missing_assumptions'] ?? []) !== []) {
            $flags[] = $this->flag('missing_assumptions', 'Financial assumptions need detail', 'Growth, margin, inflation, or profit-target assumptions are missing. This lowers the confidence score.', 'medium');
        }

        if (($confidence['row_confidence']['guess_ratio'] ?? 0) >= 0.5) {
            $flags[] = $this->flag('too_many_guesses', 'Too many guessed rows', 'Replace the highest-value guesses with uploaded evidence, advisor-reviewed estimates, or client-confirmed figures.', 'medium');
        }

        $snapshotDiscrepancy = $this->financialSnapshotDiscrepancy($budget, $computed);
        if ($snapshotDiscrepancy !== null) {
            $flags[] = $this->flag(
                'financial_snapshot_discrepancy',
                'Forecast differs from latest financial snapshot',
                sprintf(
                    'Year 1 budget revenue is %s while the latest accounting snapshot shows %s, a %s variance.',
                    $this->money((float) $snapshotDiscrepancy['budget_revenue']),
                    $this->money((float) $snapshotDiscrepancy['snapshot_revenue']),
                    number_format((float) $snapshotDiscrepancy['variance_percent'], 1).'%',
                ),
                'medium',
            );
        }

        if (($computed['input_count'] ?? 0) > 0 && ! (bool) ($computed['break_even_reached'] ?? false)) {
            $flags[] = $this->flag('no_break_even', 'Break-even not visible', 'The current budget does not yet show a break-even year. This should be addressed before relying on the proposal.', 'medium');
        }

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $flags[] = $this->flag('tax_not_configured', 'Company tax rate not configured', 'After-tax profit uses a warning state until Admin reference data has a current company tax rate.', 'medium');
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array{budget_revenue:float,snapshot_revenue:float,variance_percent:float}|null
     */
    private function financialSnapshotDiscrepancy(StrategicBudget $budget, array $computed): ?array
    {
        $budgetRevenue = (float) data_get($computed, 'annual_totals.0.revenue', 0);
        if ($budgetRevenue <= 0) {
            return null;
        }

        $snapshot = FinancialSnapshot::query()
            ->where('client_id', $budget->client_id)
            ->latest('period_end')
            ->latest('pulled_at')
            ->first();

        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        $snapshotRevenue = (float) data_get($snapshot->profit_and_loss, 'revenue', 0);
        if ($snapshotRevenue <= 0) {
            return null;
        }

        $variance = abs($budgetRevenue - $snapshotRevenue) / $snapshotRevenue;
        $threshold = (float) config('entrepreneurs.budget.snapshot_revenue_variance_threshold', 0.2);

        if ($variance < $threshold) {
            return null;
        }

        return [
            'budget_revenue' => round($budgetRevenue, 2),
            'snapshot_revenue' => round($snapshotRevenue, 2),
            'variance_percent' => round($variance * 100, 2),
        ];
    }

    /**
     * @return array{key:string,title:string,message:string,severity:string}
     */
    private function flag(string $key, string $title, string $message, string $severity): array
    {
        return compact('key', 'title', 'message', 'severity');
    }

    private function pathway(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE => StrategicBudget::PATHWAY_DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => StrategicBudget::PATHWAY_POST_ACQUISITION,
            EngagementType::NPO => StrategicBudget::PATHWAY_NPO,
            default => StrategicBudget::PATHWAY_ADVISORY,
        };
    }

    private function label(string $pathway): string
    {
        return $pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan & Budget'
            : 'Business Plan & Budget';
    }

    private function defaultHorizonMonths(Client $client): int
    {
        return match ($client->engagement_type) {
            EngagementType::DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => 24,
            default => 12,
        };
    }

    private function horizonMonths(mixed $value): int
    {
        $months = is_numeric($value) ? (int) $value : 12;

        return in_array($months, [12, 24, 36], true) ? $months : 12;
    }

    private function expectedRunway(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? min(60, max(0, (int) $value)) : null;
    }

    private function economicPercent(string $indicator): ?float
    {
        try {
            $value = EconomicIndicator::query()
                ->where('indicator', $indicator)
                ->latest('period_date')
                ->latest('fetched_at')
                ->value('value');
        } catch (QueryException) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function confidenceMessage(int $score, bool $hasFinancials): string
    {
        if (! $hasFinancials) {
            return 'Upload and verify a P&L or management accounts file to unlock this budget.';
        }

        return match (true) {
            $score >= 80 => 'Budget confidence is strong enough for advisor proposal readiness review.',
            $score >= 55 => 'Budget confidence is developing; review flagged assumptions before proposal generation.',
            default => 'Budget confidence is preliminary and will adversely affect the business plan and proposal unless improved.',
        };
    }
}
