<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\ProofOfCompletion;
use App\Models\User;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Services\Audit\AuditWriter;
use App\Services\Documents\DocumentVerificationGate;
use App\Services\Pv\PvEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GoalTracker
{
    public function __construct(
        private readonly PvEngine $pv,
        private readonly DocumentVerifier $verifier,
        private readonly DocumentVerificationGate $verificationGate,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createGoal(Client $client, array $input, ?User $actor = null): Goal
    {
        return DB::transaction(function () use ($client, $input, $actor): Goal {
            $pvCalculation = null;
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
            }

            $goal = Goal::query()->create([
                'client_id' => $client->getKey(),
                'title' => $this->requiredString($input, 'title'),
                'description' => $this->nullableString($input['description'] ?? null),
                'pv_target_calculation_id' => $pvCalculation?->getKey(),
                'pv_target' => round($pvTarget, 2),
                'status' => Goal::STATUS_ACTIVE,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            $this->audit->record('goal.created', subject: $goal, actor: $actor, after: [
                'pv_target' => $goal->pv_target,
                'pv_target_calculation_id' => $goal->pv_target_calculation_id,
            ]);

            return $goal->refresh();
        });
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
        $action = MilestoneAction::query()->create([
            'milestone_id' => $milestone->getKey(),
            'client_id' => $milestone->client_id,
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
        $goals = Goal::query()
            ->with(['milestones.actions', 'milestones.proofOfCompletion'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->get();

        return [
            'pv_realised_total' => $this->pvRealisedTotal($client),
            'active_goals' => $goals->where('status', Goal::STATUS_ACTIVE)->count(),
            'goals' => $goals
                ->map(fn (Goal $goal): array => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'description' => $goal->description,
                    'pv_target' => $goal->pv_target,
                    'status' => $goal->status,
                    ...($includeAdvisorActions ? [
                        'milestone_store_url' => route('advisor.goals.milestones.store', $goal, absolute: false),
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
                ])
                ->values()
                ->all(),
        ];
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

    private function proofStatus(DocumentVerification $verification): string
    {
        return $verification->outcome === DocumentVerification::OUTCOME_VERIFIED
            ? ProofOfCompletion::STATUS_VERIFIED
            : ProofOfCompletion::STATUS_FLAGGED;
    }
}
