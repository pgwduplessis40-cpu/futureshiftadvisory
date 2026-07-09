<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\BusinessValuation as BusinessValuationModel;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\NpoEngagement;
use App\Models\ProofOfCompletion;
use App\Models\User;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Services\Audit\AuditWriter;
use App\Services\Calendar\ClientAvailabilityCalendar;
use App\Services\Calendar\PublicHolidayCalendar;
use App\Services\Documents\DocumentVerificationGate;
use App\Services\Pv\BusinessValuation as BusinessValuationService;
use App\Services\Pv\PvEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class GoalTracker
{
    public function __construct(
        private readonly PvEngine $pv,
        private readonly BusinessValuationService $valuations,
        private readonly DocumentVerifier $verifier,
        private readonly DocumentVerificationGate $verificationGate,
        private readonly AuditWriter $audit,
        private readonly PublicHolidayCalendar $publicHolidays,
        private readonly ClientAvailabilityCalendar $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createGoal(Client $client, array $input, ?User $actor = null): Goal
    {
        return DB::transaction(function () use ($client, $input, $actor): Goal {
            $pvCalculation = null;
            $baselineValuation = $this->latestBusinessValuation($client);
            $pvTarget = (float) ($input['pv_target'] ?? 0);

            if ($this->hasCashFlows($input)) {
                $pvCalculation = $this->pv->calculate(
                    client: $client,
                    type: PvType::GoalTarget,
                    discountMethod: $this->discountMethod($input),
                    cashFlows: $this->cashFlows($input),
                    discountOptions: $this->discountOptions($input, 'Advisor default goal PV target discount rate.'),
                );
                $pvTarget = (float) $pvCalculation->result['present_value'];
            } elseif (is_numeric($input['target_growth_percent'] ?? null) && $baselineValuation instanceof BusinessValuationModel) {
                $growthRate = max(0.0, (float) $input['target_growth_percent']) / 100;
                $pvTarget = round($baselineValuation->reconciled_mid * (1 + $growthRate), 2);
            }

            $goal = Goal::query()->create([
                'client_id' => $client->getKey(),
                'title' => $this->requiredString($input, 'title'),
                'description' => $this->nullableString($input['description'] ?? null),
                'pv_target_calculation_id' => $pvCalculation?->getKey(),
                'baseline_business_valuation_id' => $baselineValuation?->getKey(),
                'latest_business_valuation_id' => $baselineValuation?->getKey(),
                'pv_target' => round($pvTarget, 2),
                'target_date' => $this->targetDate($input),
                'target_growth_percent' => $this->targetGrowthPercent($input),
                'status' => Goal::STATUS_ACTIVE,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            $this->audit->record('goal.created', subject: $goal, actor: $actor, after: [
                'pv_target' => $goal->pv_target,
                'pv_target_calculation_id' => $goal->pv_target_calculation_id,
                'baseline_business_valuation_id' => $goal->baseline_business_valuation_id,
                'target_date' => $goal->target_date?->toDateString(),
                'target_growth_percent' => $goal->target_growth_percent,
            ]);

            return $goal->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $valuationOptions
     */
    public function remeasureGoal(Goal $goal, array $valuationOptions = [], ?User $actor = null): Goal
    {
        $goal->loadMissing('client');
        $client = $goal->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Goal must belong to a client.');
        }

        return DB::transaction(function () use ($goal, $client, $valuationOptions, $actor): Goal {
            $options = $this->valuationOptionsForRemeasurement($goal, $valuationOptions);
            $valuation = $this->valuations->calculate($client, $options);
            $baselineValuationId = $goal->baseline_business_valuation_id ?: $valuation->getKey();

            $goal->forceFill([
                'baseline_business_valuation_id' => $baselineValuationId,
                'latest_business_valuation_id' => $valuation->getKey(),
                'pv_remeasurement_failure_count' => 0,
                'pv_remeasurement_failed_at' => null,
                'pv_remeasurement_next_retry_at' => null,
                'pv_remeasurement_failure_reason' => null,
            ])->save();

            $this->audit->record('goal.pv_remeasured', subject: $goal, actor: $actor, after: [
                'baseline_business_valuation_id' => $baselineValuationId,
                'latest_business_valuation_id' => $valuation->getKey(),
                'current_pv' => $valuation->reconciled_mid,
                'target_pv' => $goal->pv_target,
                'valuation_options_source' => $options['methodology_source'] ?? 'baseline_or_explicit',
            ]);

            return $goal->refresh();
        });
    }

    public function confirmAchieved(Goal $goal, User $actor): Goal
    {
        if ($goal->status === Goal::STATUS_ABANDONED) {
            throw new InvalidArgumentException('Abandoned goals cannot be marked achieved.');
        }

        $goal->forceFill([
            'status' => Goal::STATUS_ACHIEVED,
            'achieved_at' => now(),
            'achieved_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('goal.achieved', subject: $goal, actor: $actor, after: [
            'pv_target' => $goal->pv_target,
            'measurement' => $this->measurementPayload($goal->refresh()->load(['baselineBusinessValuation', 'latestBusinessValuation']), $this->goalRealisedTotal($goal)),
        ]);

        return $goal->refresh();
    }

    public function remeasureDueGoals(int $limit = 50): array
    {
        $goals = Goal::query()
            ->where('status', Goal::STATUS_ACTIVE)
            ->whereNotNull('target_date')
            ->whereDate('target_date', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('pv_remeasurement_next_retry_at')
                    ->orWhere('pv_remeasurement_next_retry_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('latest_business_valuation_id')
                    ->orWhereDoesntHave('latestBusinessValuation')
                    ->orWhereHas('latestBusinessValuation', function ($valuation): void {
                        $valuation->whereColumn('business_valuations.as_at', '<', 'goals.target_date');
                    });
            })
            ->with('client')
            ->oldest('target_date')
            ->limit(max(1, $limit))
            ->get();

        $result = [
            'scanned' => $goals->count(),
            'remeasured' => 0,
            'failed' => 0,
        ];

        foreach ($goals as $goal) {
            try {
                $this->remeasureGoal($goal, ['remeasurement_source' => 'scheduled']);
                $result['remeasured']++;
            } catch (\Throwable $exception) {
                $result['failed']++;
                $this->recordRemeasurementFailure($goal, $exception);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createMilestone(Goal $goal, array $input, ?User $actor = null): Milestone
    {
        $goal->loadMissing('client');
        $client = $goal->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Goal must belong to a client.');
        }

        $this->assertDueDateAllowed($client, $input['due_date'] ?? null, 'Milestones', 'due_date');

        return DB::transaction(function () use ($goal, $client, $input, $actor): Milestone {
            $pvCalculation = null;
            $pvOfImpact = (float) ($input['pv_of_impact'] ?? 0);

            if ($this->hasCashFlows($input)) {
                $pvCalculation = $this->pv->calculate(
                    client: $client,
                    type: PvType::MilestoneImpact,
                    discountMethod: $this->discountMethod($input),
                    cashFlows: $this->cashFlows($input),
                    discountOptions: $this->discountOptions($input, 'Advisor default milestone PV impact discount rate.'),
                );
                $pvOfImpact = (float) $pvCalculation->result['present_value'];
            }

            $milestone = Milestone::query()->create([
                'goal_id' => $goal->getKey(),
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $this->npoEngagementId($client, $input['npo_engagement_id'] ?? null),
                'title' => $this->requiredString($input, 'title'),
                'recommendation_ref' => $this->nullableString($input['recommendation_ref'] ?? null),
                'pv_of_impact_calculation_id' => $pvCalculation?->getKey(),
                'pv_of_impact' => round($pvOfImpact, 2),
                'due_date' => $input['due_date'] ?? null,
                'status' => Milestone::STATUS_PENDING,
            ]);

            $this->audit->record('milestone.created', subject: $milestone, actor: $actor, after: [
                'goal_id' => $goal->getKey(),
                'pv_of_impact' => $milestone->pv_of_impact,
                'pv_of_impact_calculation_id' => $milestone->pv_of_impact_calculation_id,
            ]);

            return $milestone->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAction(Milestone $milestone, array $input, ?User $actor = null): MilestoneAction
    {
        $milestone->loadMissing('client');
        $client = $milestone->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Milestone must belong to a client.');
        }

        $this->assertDueDateAllowed($client, $input['due_date'] ?? null, 'Actions', 'due_date');

        $action = MilestoneAction::query()->create([
            'milestone_id' => $milestone->getKey(),
            'client_id' => $milestone->client_id,
            'npo_engagement_id' => $milestone->npo_engagement_id,
            'title' => $this->requiredString($input, 'title'),
            'owner_user_id' => $input['owner_user_id'] ?? null,
            'due_date' => $input['due_date'] ?? null,
            'priority' => (string) ($input['priority'] ?? 'normal'),
            'status' => MilestoneAction::STATUS_PENDING,
        ]);

        $this->audit->record('milestone_action.created', subject: $action, actor: $actor, after: [
            'milestone_id' => $milestone->getKey(),
        ]);

        return $action->refresh();
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    public function completeWithProof(Milestone $milestone, Document $document, array $claim = [], ?User $actor = null): ProofOfCompletion
    {
        if ((string) $milestone->client_id !== (string) $document->client_id) {
            throw new InvalidArgumentException('Proof document must belong to the milestone client.');
        }

        if ($document->scanner_result !== Document::SCANNER_CLEAN) {
            throw new InvalidArgumentException('Proof document must be clean before completing the milestone.');
        }

        return DB::transaction(function () use ($milestone, $document, $claim, $actor): ProofOfCompletion {
            $claim = array_filter($claim, static fn (mixed $value): bool => $value !== null);

            $verification = $this->verifier->verify($document, [
                'source' => 'proof_of_completion',
                'claim' => $claim['claim'] ?? "Proof that milestone [{$milestone->title}] is complete.",
                'milestone_id' => $milestone->getKey(),
                ...$claim,
            ]);

            $proof = ProofOfCompletion::query()->create([
                'milestone_id' => $milestone->getKey(),
                'client_id' => $milestone->client_id,
                'document_id' => $document->getKey(),
                'document_verification_id' => $verification->getKey(),
                'status' => $this->proofStatus($verification),
                'reviewed_at' => $verification->verified_at,
            ]);

            $proofBlocksCompletion = $this->verificationGate
                ->blockingFlags((string) $milestone->client_id)
                ->contains(fn (DocumentVerification $flag): bool => $flag->is($verification));

            if ($proofBlocksCompletion) {
                $milestone->forceFill([
                    'status' => Milestone::STATUS_BLOCKED,
                    'completed_at' => null,
                ])->save();

                $this->audit->record('milestone.proof_flagged', subject: $proof, actor: $actor, after: [
                    'milestone_id' => $milestone->getKey(),
                    'verification_outcome' => $verification->outcome,
                ]);

                return $proof->refresh();
            }

            $milestone->forceFill([
                'status' => Milestone::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            $this->audit->record('milestone.completed', subject: $milestone, actor: $actor, after: [
                'proof_of_completion_id' => $proof->getKey(),
                'document_verification_id' => $verification->getKey(),
                'pv_of_impact' => $milestone->pv_of_impact,
            ]);

            return $proof->refresh();
        });
    }

    public function pvRealisedTotal(Client $client): float
    {
        return round((float) Milestone::query()
            ->where('client_id', $client->getKey())
            ->where('status', Milestone::STATUS_COMPLETED)
            ->sum('pv_of_impact'), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Client $client, bool $includeAdvisorActions = false): array
    {
        return $this->dashboardPayload($client, $includeAdvisorActions);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardForEngagement(Client $client, NpoEngagement $engagement, bool $includeAdvisorActions = false): array
    {
        if ((string) $engagement->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('Goal dashboard engagement must belong to the client.');
        }

        return $this->dashboardPayload($client, $includeAdvisorActions, $engagement);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(Client $client, bool $includeAdvisorActions = false, ?NpoEngagement $engagement = null): array
    {
        $goals = Goal::query()
            ->with([
                'baselineBusinessValuation.pvCalculation',
                'latestBusinessValuation.pvCalculation',
                'milestones.actions',
                'milestones.proofOfCompletion',
            ])
            ->where('client_id', $client->getKey())
            ->latest()
            ->get()
            ->map(function (Goal $goal) use ($engagement): Goal {
                if ($engagement instanceof NpoEngagement) {
                    $goal->setRelation(
                        'milestones',
                        $goal->milestones
                            ->where('npo_engagement_id', $engagement->getKey())
                            ->values(),
                    );
                }

                return $goal;
            })
            ->filter(fn (Goal $goal): bool => ! ($engagement instanceof NpoEngagement) || $goal->milestones->isNotEmpty())
            ->values();
        $completedTotal = $engagement instanceof NpoEngagement
            ? round((float) Milestone::query()
                ->where('client_id', $client->getKey())
                ->where('npo_engagement_id', $engagement->getKey())
                ->where('status', Milestone::STATUS_COMPLETED)
                ->sum('pv_of_impact'), 2)
            : $this->pvRealisedTotal($client);

        return [
            'pv_realised_total' => $completedTotal,
            'active_goals' => $goals->where('status', Goal::STATUS_ACTIVE)->count(),
            'goals' => $goals
                ->map(fn (Goal $goal): array => $this->goalPayload($goal, $includeAdvisorActions))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function goalPayload(Goal $goal, bool $includeAdvisorActions): array
    {
        $realised = $this->goalRealisedTotal($goal);

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
            'pv_target' => $goal->pv_target,
            'target_date' => $goal->target_date?->toDateString(),
            'target_growth_percent' => $goal->target_growth_percent,
            'status' => $goal->status,
            'achieved_at' => $goal->achieved_at?->toIso8601String(),
            'measurement' => $this->measurementPayload($goal, $realised),
            ...($includeAdvisorActions ? [
                'milestone_store_url' => route('advisor.goals.milestones.store', $goal, absolute: false),
                'remeasure_url' => route('advisor.goals.remeasure', $goal, absolute: false),
                'achieve_url' => route('advisor.goals.achieve', $goal, absolute: false),
            ] : []),
            'milestones' => $goal->milestones
                ->map(fn (Milestone $milestone): array => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'recommendation_ref' => $milestone->recommendation_ref,
                    'pv_of_impact' => $milestone->pv_of_impact,
                    'status' => $milestone->status,
                    'due_date' => $milestone->due_date?->toDateString(),
                    'completed_at' => $milestone->completed_at?->toIso8601String(),
                    'actions_count' => $milestone->actions->count(),
                    'proof_status' => $milestone->proofOfCompletion->last()?->status,
                    ...($includeAdvisorActions ? [
                        'action_store_url' => route('advisor.milestones.actions.store', $milestone, absolute: false),
                        'proof_store_url' => route('advisor.milestones.proof.store', $milestone, absolute: false),
                    ] : []),
                ])
                ->values()
                ->all(),
        ];
    }

    private function goalRealisedTotal(Goal $goal): float
    {
        return round((float) $goal->milestones
            ->where('status', Milestone::STATUS_COMPLETED)
            ->sum('pv_of_impact'), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function measurementPayload(Goal $goal, float $realised): array
    {
        $baseline = $goal->baselineBusinessValuation;
        $current = $goal->latestBusinessValuation;
        $baselineValue = $baseline instanceof BusinessValuationModel ? round($baseline->reconciled_mid, 2) : null;
        $currentValue = $current instanceof BusinessValuationModel ? round($current->reconciled_mid, 2) : null;
        $movement = $baselineValue !== null && $currentValue !== null
            ? round($currentValue - $baselineValue, 2)
            : null;
        $target = round((float) $goal->pv_target, 2);
        $targetGap = $currentValue !== null && $target > 0
            ? round($target - $currentValue, 2)
            : null;

        return [
            'baseline_pv' => $baselineValue,
            'baseline_as_at' => $baseline?->as_at?->toIso8601String(),
            'baseline_business_valuation_id' => $baseline?->getKey(),
            'baseline_pv_calculation_id' => $baseline?->pv_calculation_id,
            'current_pv' => $currentValue,
            'current_as_at' => $current?->as_at?->toIso8601String(),
            'current_business_valuation_id' => $current?->getKey(),
            'current_pv_calculation_id' => $current?->pv_calculation_id,
            'pv_movement' => $movement,
            'target_gap' => $targetGap,
            'progress_percent' => $this->progressPercent($baselineValue, $currentValue, $target),
            'realised_pv' => $realised,
            'realised_explains_percent' => $this->realisedExplainsPercent($movement, $realised),
            'due_for_remeasurement' => $this->dueForRemeasurement($goal, $current),
        ];
    }

    private function latestBusinessValuation(Client $client): ?BusinessValuationModel
    {
        return BusinessValuationModel::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    private function recordRemeasurementFailure(Goal $goal, \Throwable $exception): void
    {
        $failureCount = min(30, ((int) $goal->pv_remeasurement_failure_count) + 1);
        $retryDays = min(7, 2 ** min(3, $failureCount - 1));
        $nextRetryAt = now()->addDays($retryDays);
        $message = str($exception->getMessage())->limit(1000)->toString();

        $goal->forceFill([
            'pv_remeasurement_failure_count' => $failureCount,
            'pv_remeasurement_failed_at' => now(),
            'pv_remeasurement_next_retry_at' => $nextRetryAt,
            'pv_remeasurement_failure_reason' => $message,
        ])->save();

        $this->audit->record('goal.pv_remeasurement_failed', subject: $goal, after: [
            'message' => $message,
            'failure_count' => $failureCount,
            'next_retry_at' => $nextRetryAt->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $valuationOptions
     * @return array<string, mixed>
     */
    private function valuationOptionsForRemeasurement(Goal $goal, array $valuationOptions): array
    {
        $goal->loadMissing([
            'baselineBusinessValuation.pvCalculation',
        ]);

        $baseline = $goal->baselineBusinessValuation;
        $baselineOptions = $baseline instanceof BusinessValuationModel
            ? $this->baselineValuationOptions($baseline)
            : [];

        unset($valuationOptions['remeasurement_source']);

        return [
            ...$baselineOptions,
            ...$valuationOptions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineValuationOptions(BusinessValuationModel $baseline): array
    {
        $calculation = $baseline->pvCalculation;
        $options = [
            'methodology_source' => 'baseline_business_valuation:'.$baseline->getKey(),
        ];

        if (is_array($baseline->method_weights)) {
            $options['method_weights'] = $baseline->method_weights;
        }

        if (is_array($baseline->method_rationale)) {
            $options['method_rationale'] = $baseline->method_rationale;
        }

        if (is_array($baseline->adjustments)) {
            $options['adjustments'] = $baseline->adjustments;
        }

        if (is_array($baseline->equity_bridge)) {
            $bridgeAdjustments = $baseline->equity_bridge['bridge_adjustments'] ?? null;

            if (is_array($bridgeAdjustments)) {
                $options['equity_bridge'] = $bridgeAdjustments;
            }
        }

        $industryCode = data_get($baseline->sde_value, 'multiple.industry_code')
            ?? data_get($baseline->ebitda_value, 'multiple.industry_code');
        if (is_string($industryCode) && trim($industryCode) !== '') {
            $options['industry_code'] = strtoupper(trim($industryCode));
        }

        $terminalGrowthRate = data_get($calculation?->result, 'terminal_growth_rate');
        if (is_numeric($terminalGrowthRate)) {
            $options['terminal_growth_rate'] = (float) $terminalGrowthRate;
        }

        if ($calculation !== null) {
            $options['discount_method'] = $calculation->discount_method instanceof DiscountMethod
                ? $calculation->discount_method
                : DiscountMethod::AdvisorConfigured;
            $options['discount_options'] = [
                ...(array) data_get($calculation->inputs, 'discount_options', []),
                'rate' => $calculation->discount_rate,
                'rationale' => $calculation->discount_rate_rationale,
                'source_reference' => 'baseline_pv_calculation:'.$calculation->getKey(),
            ];

            $sensitivityRates = collect(data_get($baseline->dcf_sensitivity, 'rows', []))
                ->pluck('discount_rate')
                ->filter(fn (mixed $rate): bool => is_numeric($rate))
                ->map(fn (mixed $rate): float => (float) $rate)
                ->unique()
                ->values()
                ->all();
            if ($sensitivityRates !== []) {
                $options['sensitivity_discount_rates'] = $sensitivityRates;
            }

            $sensitivityGrowthRates = collect(data_get($baseline->dcf_sensitivity, 'rows', []))
                ->pluck('terminal_growth_rate')
                ->filter(fn (mixed $rate): bool => is_numeric($rate))
                ->map(fn (mixed $rate): float => (float) $rate)
                ->unique()
                ->values()
                ->all();
            if ($sensitivityGrowthRates !== []) {
                $options['sensitivity_terminal_growth_rates'] = $sensitivityGrowthRates;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function targetDate(array $input): ?string
    {
        $targetDate = $input['target_date'] ?? null;

        if (is_string($targetDate) && trim($targetDate) !== '') {
            return $targetDate;
        }

        $horizonMonths = $input['horizon_months'] ?? null;

        if (is_numeric($horizonMonths) && (int) $horizonMonths > 0) {
            return now()->addMonths((int) $horizonMonths)->toDateString();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function targetGrowthPercent(array $input): ?float
    {
        if (! is_numeric($input['target_growth_percent'] ?? null)) {
            return null;
        }

        return round(max(0.0, (float) $input['target_growth_percent']), 4);
    }

    private function progressPercent(?float $baselineValue, ?float $currentValue, float $target): ?float
    {
        if ($currentValue === null || $target <= 0) {
            return null;
        }

        if ($baselineValue !== null && $target > $baselineValue) {
            return round(max(0.0, min(100.0, (($currentValue - $baselineValue) / ($target - $baselineValue)) * 100)), 1);
        }

        return round(max(0.0, min(100.0, ($currentValue / $target) * 100)), 1);
    }

    private function realisedExplainsPercent(?float $movement, float $realised): ?float
    {
        if ($movement === null || $movement <= 0.0) {
            return null;
        }

        return round(max(0.0, min(100.0, ($realised / $movement) * 100)), 1);
    }

    private function dueForRemeasurement(Goal $goal, ?BusinessValuationModel $current): bool
    {
        if ($goal->status !== Goal::STATUS_ACTIVE || $goal->target_date === null) {
            return false;
        }

        if ($goal->target_date->isFuture()) {
            return false;
        }

        return ! $current instanceof BusinessValuationModel
            || $current->as_at === null
            || $current->as_at->lt($goal->target_date->copy()->startOfDay());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasCashFlows(array $input): bool
    {
        return is_array($input['cash_flows'] ?? null)
            || is_numeric($input['annual_benefit'] ?? null)
            || is_numeric($input['annual_impact'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, float>
     */
    private function cashFlows(array $input): array
    {
        if (is_array($input['cash_flows'] ?? null)) {
            return array_map('floatval', $input['cash_flows']);
        }

        $amount = (float) ($input['annual_benefit'] ?? ($input['annual_impact'] ?? 0));
        $duration = max(1, min(10, (int) ($input['duration_years'] ?? 1)));

        return array_fill(1, $duration, $amount);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function discountMethod(array $input): DiscountMethod
    {
        $candidate = $input['discount_method'] ?? DiscountMethod::AdvisorConfigured->value;

        return $candidate instanceof DiscountMethod
            ? $candidate
            : DiscountMethod::from((string) $candidate);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function discountOptions(array $input, string $defaultRationale): array
    {
        $options = is_array($input['discount_options'] ?? null) ? $input['discount_options'] : [];

        return [
            'rate' => (float) ($options['rate'] ?? $input['discount_rate'] ?? 0.12),
            'rationale' => (string) ($options['rationale'] ?? $defaultRationale),
            ...$options,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredString(array $input, string $key): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function npoEngagementId(Client $client, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereKey($value)
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            throw new InvalidArgumentException('Milestone NPO engagement must belong to the goal client.');
        }

        return (string) $engagement->getKey();
    }

    private function assertDueDateAllowed(Client $client, mixed $dueDate, string $subject, string $field): void
    {
        if ($dueDate === null || trim((string) $dueDate) === '') {
            return;
        }

        $holiday = $this->publicHolidays->holidayOn(
            $dueDate,
            $this->publicHolidays->regionsForClient($client),
        );

        if ($holiday !== null) {
            throw ValidationException::withMessages([
                $field => $this->publicHolidays->validationMessage($holiday, $subject),
            ]);
        }

        $this->availability->assertAvailable($client, $dueDate, $subject, $field);
    }

    private function proofStatus(DocumentVerification $verification): string
    {
        return $verification->outcome === DocumentVerification::OUTCOME_VERIFIED
            ? ProofOfCompletion::STATUS_VERIFIED
            : ProofOfCompletion::STATUS_FLAGGED;
    }
}
