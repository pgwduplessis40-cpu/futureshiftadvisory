<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningRecommendation;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateDecision;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LearningRecommendationWorkflow
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly LearningUpdatePlainEnglishSummary $plainEnglish,
        private readonly LearningCapabilityProfile $capabilities,
        private readonly BudgetLearningSignal $learningSignals,
        private readonly PendingLearningDeveloperBrief $pendingBrief,
    ) {}

    /**
     * @param  array{title:string,failure_shortfall:string,impact:string,impact_area:string,recommendation:string,recommendation_impact:string,acceptance_criteria?:array<int,string>|null,regression_journeys?:array<int,string>|null,delivery_owner?:?string,delivery_target?:?string,baseline_metrics?:array<int,string>|null,rollback_plan?:?string}  $input
     */
    public function draft(LearningUpdate $update, User $actor, array $input): LearningRecommendation
    {
        return DB::transaction(function () use ($actor, $input, $update): LearningRecommendation {
            /** @var LearningUpdate $locked */
            $locked = LearningUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();
            $existing = $locked->recommendations()->whereNotIn('status', [
                LearningRecommendation::STATUS_REJECTED,
                LearningRecommendation::STATUS_ROLLED_BACK,
            ])->latest()->first();

            if ($existing instanceof LearningRecommendation) {
                throw ValidationException::withMessages([
                    'learning_update' => 'This learning already has an active recommendation. Update or deliver that recommendation instead of creating a duplicate.',
                ]);
            }

            /** @var LearningRecommendation $recommendation */
            $recommendation = $locked->recommendations()->create([
                'title' => $input['title'],
                'failure_shortfall' => $input['failure_shortfall'],
                'impact' => $input['impact'],
                'impact_area' => $input['impact_area'],
                'recommendation' => $input['recommendation'],
                'recommendation_impact' => $input['recommendation_impact'],
                'acceptance_criteria' => $input['acceptance_criteria'] ?? [],
                'regression_journeys' => $input['regression_journeys'] ?? [],
                'delivery_owner' => $this->stringInput($input['delivery_owner'] ?? null),
                'delivery_target' => $this->stringInput($input['delivery_target'] ?? null),
                'baseline_metrics' => $input['baseline_metrics'] ?? [],
                'rollback_plan' => $this->stringInput($input['rollback_plan'] ?? null),
                'evidence' => $this->evidenceFor($locked),
                'status' => LearningRecommendation::STATUS_DRAFT,
            ]);

            if ($locked->status === LearningUpdate::STATUS_DETECTED) {
                $locked->forceFill(['status' => LearningUpdate::STATUS_STAGED])->save();
            }

            $this->audit->record('learning_recommendation.drafted', subject: $recommendation, actor: $actor, after: [
                'learning_update_id' => $locked->getKey(),
                'impact_area' => $recommendation->impact_area,
                'acceptance_criteria_count' => count($recommendation->acceptance_criteria ?? []),
                'regression_journeys' => $recommendation->regression_journeys ?? [],
            ]);

            return $recommendation->refresh();
        });
    }

    public function approve(LearningRecommendation $recommendation, User $actor): LearningRecommendation
    {
        return DB::transaction(function () use ($actor, $recommendation): LearningRecommendation {
            /** @var LearningRecommendation $locked */
            $locked = LearningRecommendation::query()->with('learningUpdate')->whereKey($recommendation->getKey())->lockForUpdate()->firstOrFail();

            return $this->approveLocked($locked, $actor);
        });
    }

    /**
     * Approve a deliberate selection together or make no change at all when
     * one of those drafts has already moved to another state.
     *
     * @param  array<int, string>  $recommendationIds
     * @return Collection<int, LearningRecommendation>
     */
    public function approveMany(array $recommendationIds, User $actor): Collection
    {
        $ids = collect($recommendationIds)
            ->map(fn (string $id): string => trim($id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'recommendation_ids' => 'Select one or more draft recommendations to approve for development.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ids): Collection {
            /** @var Collection<int, LearningRecommendation> $locked */
            $locked = LearningRecommendation::query()
                ->with('learningUpdate')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($locked->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'recommendation_ids' => 'One or more selected recommendations are unavailable. Refresh the queue and try again.',
                ]);
            }

            if ($locked->contains(fn (LearningRecommendation $recommendation): bool => $recommendation->status !== LearningRecommendation::STATUS_DRAFT)) {
                throw ValidationException::withMessages([
                    'recommendation_ids' => 'Only draft recommendations can be approved for development. Refresh the queue and try again.',
                ]);
            }

            return $locked
                ->map(fn (LearningRecommendation $recommendation): LearningRecommendation => $this->approveLocked($recommendation, $actor))
                ->values();
        });
    }

    /**
     * @param  array{status:string,development_reference?:?string,release_reference?:?string,verification_notes?:?string,regression_journeys?:array<int,string>|null,delivery_owner?:?string,delivery_target?:?string,baseline_metrics?:array<int,string>|null,rollback_plan?:?string}  $input
     */
    public function updateDelivery(LearningRecommendation $recommendation, User $actor, array $input): LearningRecommendation
    {
        return DB::transaction(function () use ($actor, $input, $recommendation): LearningRecommendation {
            /** @var LearningRecommendation $locked */
            $locked = LearningRecommendation::query()->with('learningUpdate')->whereKey($recommendation->getKey())->lockForUpdate()->firstOrFail();
            $status = (string) $input['status'];
            $this->assertDeliveryTransition($locked, $status);
            $developmentReference = $this->stringInput($input['development_reference'] ?? $locked->development_reference);
            $releaseReference = $this->stringInput($input['release_reference'] ?? $locked->release_reference);
            $verificationNotes = $this->stringInput($input['verification_notes'] ?? $locked->verification_notes);
            $regressionJourneys = $input['regression_journeys'] ?? $locked->regression_journeys ?? [];
            $deliveryOwner = $this->stringInput($input['delivery_owner'] ?? $locked->delivery_owner);
            $deliveryTarget = $this->stringInput($input['delivery_target'] ?? $locked->delivery_target);
            $baselineMetrics = $input['baseline_metrics'] ?? $locked->baseline_metrics ?? [];
            $rollbackPlan = $this->stringInput($input['rollback_plan'] ?? $locked->rollback_plan);
            $recordsDeliveryEvidence = in_array($status, [
                LearningRecommendation::STATUS_IN_DEVELOPMENT,
                LearningRecommendation::STATUS_RELEASED,
            ], true);
            if ($recordsDeliveryEvidence && $developmentReference === null) {
                throw ValidationException::withMessages(['development_reference' => 'Record the development issue, pull request, or commit before recording delivery.']);
            }
            if ($recordsDeliveryEvidence && $deliveryOwner === null) {
                throw ValidationException::withMessages(['delivery_owner' => 'Assign a delivery owner before recording delivery.']);
            }
            if ($recordsDeliveryEvidence && $deliveryTarget === null) {
                throw ValidationException::withMessages(['delivery_target' => 'Record the intended delivery target before recording delivery.']);
            }
            if ($recordsDeliveryEvidence && ! $this->hasListItems($baselineMetrics)) {
                throw ValidationException::withMessages(['baseline_metrics' => 'Record at least one baseline metric before recording delivery.']);
            }
            if ($recordsDeliveryEvidence && $rollbackPlan === null) {
                throw ValidationException::withMessages(['rollback_plan' => 'Record a rollback plan before recording delivery.']);
            }
            if ($status === LearningRecommendation::STATUS_RELEASED && $releaseReference === null) {
                throw ValidationException::withMessages(['release_reference' => 'Record the deployment or version evidence before marking this recommendation released.']);
            }
            if ($status === LearningRecommendation::STATUS_RELEASED && ! $this->hasRegressionJourneys($regressionJourneys)) {
                throw ValidationException::withMessages(['regression_journeys' => 'Record the affected client journeys that passed regression checks before marking this recommendation released.']);
            }
            if ($status === LearningRecommendation::STATUS_VERIFIED && $verificationNotes === null) {
                throw ValidationException::withMessages(['verification_notes' => 'Record verification evidence before marking this recommendation addressed.']);
            }

            $now = now();
            $updates = [
                'status' => $status,
                'development_reference' => $developmentReference,
                'release_reference' => $releaseReference,
                'regression_journeys' => $regressionJourneys,
                'verification_notes' => $verificationNotes,
                'delivery_owner' => $deliveryOwner,
                'delivery_target' => $deliveryTarget,
                'baseline_metrics' => $baselineMetrics,
                'rollback_plan' => $rollbackPlan,
            ];
            if ($status === LearningRecommendation::STATUS_RELEASED) {
                $updates['released_at'] = $now;
                $updates['review_due_at'] = $now->copy()->addDays(30);
            }
            if ($status === LearningRecommendation::STATUS_VERIFIED) {
                $updates['verified_by_user_id'] = $actor->getKey();
                $updates['verified_at'] = $now;
            }
            if ($status === LearningRecommendation::STATUS_ROLLED_BACK) {
                $updates['rolled_back_at'] = $now;
            }

            $locked->forceFill($updates)->save();
            $this->audit->record('learning_recommendation.delivery_updated', subject: $locked, actor: $actor, after: [
                'learning_update_id' => $locked->learning_update_id,
                'status' => $status,
                'development_reference' => $locked->development_reference,
                'release_reference' => $locked->release_reference,
                'delivery_owner' => $locked->delivery_owner,
                'delivery_target' => $locked->delivery_target,
                'review_due_at' => $locked->review_due_at?->toIso8601String(),
            ]);

            return $locked->refresh();
        });
    }

    /** @return Collection<int, LearningRecommendation> */
    public function activeRecommendations(): Collection
    {
        return LearningRecommendation::query()
            ->with('learningUpdate')
            ->whereIn('status', [
                LearningRecommendation::STATUS_DRAFT,
                LearningRecommendation::STATUS_APPROVED,
                LearningRecommendation::STATUS_IN_DEVELOPMENT,
                LearningRecommendation::STATUS_RELEASED,
                LearningRecommendation::STATUS_BLOCKED,
            ])
            ->orderByRaw("case status when 'blocked' then 0 when 'draft' then 1 when 'approved' then 2 when 'in_development' then 3 else 4 end")
            ->latest('approved_at')
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, LearningRecommendation>|null  $recommendations
     * @return Collection<int, LearningUpdate>
     */
    public function queueLearnings(?Collection $recommendations = null): Collection
    {
        $recommendations ??= $this->activeRecommendations();
        $coveredIds = $recommendations->pluck('learning_update_id')->all();
        $coveredSignals = $recommendations
            ->map(fn (LearningRecommendation $item): ?string => $this->learningSignals->keyForUpdate($item->learningUpdate))
            ->filter()->unique()->values()->all();

        return LearningUpdate::query()
            ->with([
                'decisions' => fn ($query) => $query->latest('decided_at'),
                'implementations' => fn ($query) => $query->latest('implemented_at'),
            ])
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
                LearningUpdate::STATUS_APPROVED,
                LearningUpdate::STATUS_IMPLEMENTED,
                LearningUpdate::STATUS_ROLLED_BACK,
            ])
            ->whereNotIn('id', $coveredIds)
            ->orderByRaw("case status when 'detected' then 0 when 'staged' then 1 when 'deferred' then 2 else 3 end")
            ->latest('created_at')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (LearningUpdate $update): bool => in_array($this->learningSignals->keyForUpdate($update), $coveredSignals, true))
            ->values();
    }

    /**
     * @param  Collection<int, LearningUpdate>|null  $learnings
     * @return Collection<int, LearningUpdate>
     */
    public function pendingLearnings(?Collection $learnings = null): Collection
    {
        return ($learnings ?? $this->queueLearnings())
            ->whereIn('status', [LearningUpdate::STATUS_DETECTED, LearningUpdate::STATUS_STAGED, LearningUpdate::STATUS_DEFERRED])
            ->values();
    }

    public function developerBrief(?CarbonInterface $generatedAt = null): string
    {
        return $this->pendingBrief->render($this->pendingLearnings(), $generatedAt ?? now());
    }

    /** @return array<string,string> */
    public function defaultsFor(LearningUpdate $update): array
    {
        $profile = $this->capabilities->forUpdate($update);
        $plain = $this->plainEnglish->forUpdate($update, $profile);
        $area = data_get($update->impact_scope, 'surface')
            ?? data_get($update->impact_scope, 'module')
            ?? collect((array) data_get($update->impact_scope, 'modules'))->filter()->join(', ')
            ?: 'To be confirmed';
        $action = data_get($update->proposed_change, 'action');

        return [
            'title' => $update->summary,
            'failure_shortfall' => $update->summary,
            'impact' => (string) $plain['why_it_matters'],
            'impact_area' => (string) $area,
            'recommendation' => is_string($action) && $action !== ''
                ? 'Review and implement the governed change: '.str($action)->replace('_', ' ')->lower().'.'
                : 'Define a tested change that addresses this learning.',
            'recommendation_impact' => 'The proposed change should improve the observed outcome without weakening evidence, approval, client-scope, or audit controls.',
        ];
    }

    private function assertDeliveryTransition(LearningRecommendation $recommendation, string $next): void
    {
        $allowed = match ($recommendation->status) {
            LearningRecommendation::STATUS_APPROVED => [
                LearningRecommendation::STATUS_IN_DEVELOPMENT,
                LearningRecommendation::STATUS_RELEASED,
            ],
            LearningRecommendation::STATUS_BLOCKED => [LearningRecommendation::STATUS_IN_DEVELOPMENT],
            LearningRecommendation::STATUS_IN_DEVELOPMENT => [LearningRecommendation::STATUS_RELEASED, LearningRecommendation::STATUS_BLOCKED],
            LearningRecommendation::STATUS_RELEASED => [LearningRecommendation::STATUS_VERIFIED, LearningRecommendation::STATUS_ROLLED_BACK, LearningRecommendation::STATUS_BLOCKED],
            default => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This delivery status transition is not permitted. Recommendations must be approved, developed, released, and then verified with evidence.',
            ]);
        }
    }

    private function approveLocked(LearningRecommendation $recommendation, User $actor): LearningRecommendation
    {
        if ($recommendation->status !== LearningRecommendation::STATUS_DRAFT) {
            throw ValidationException::withMessages(['recommendation' => 'Only a draft recommendation can be approved for development.']);
        }

        /** @var LearningUpdate $update */
        $update = $recommendation->learningUpdate;
        $approvedAt = now();
        $recommendation->forceFill([
            'status' => LearningRecommendation::STATUS_APPROVED,
            'approved_by_user_id' => $actor->getKey(),
            'approved_at' => $approvedAt,
        ])->save();
        $update->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'decided_by_user_id' => $actor->getKey(),
            'decided_at' => $approvedAt,
            // A recommendation is a development work item, never a
            // scheduled automatic change to a live policy or prompt.
            'effective_date' => null,
            'pre_implementation_notice_at' => null,
            'review_due_at' => null,
        ])->save();
        $update->decisions()->create([
            'decision' => LearningUpdateDecision::DECISION_APPROVE,
            'reason' => 'Approved for development through recommendation '.$recommendation->getKey().'.',
            'decided_by_user_id' => $actor->getKey(),
            'decided_at' => $approvedAt,
        ]);

        $this->audit->record('learning_recommendation.approved', subject: $recommendation, actor: $actor, after: [
            'learning_update_id' => $update->getKey(),
            'approved_at' => $approvedAt->toIso8601String(),
            'status' => $recommendation->status,
        ]);

        return $recommendation->refresh();
    }

    /**
     * @return array{source:array<array-key,mixed>|null,proposed_change:array<array-key,mixed>|null,impact_scope:array<array-key,mixed>|null,evidence:array<array-key,mixed>|null,confidence:?float,clients_affected:int}
     */
    private function evidenceFor(LearningUpdate $update): array
    {
        return [
            'source' => $update->source,
            'proposed_change' => $update->proposed_change,
            'impact_scope' => $update->impact_scope,
            'evidence' => $update->evidence,
            'confidence' => $update->confidence,
            'clients_affected' => $update->clients_affected,
        ];
    }

    private function stringInput(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function hasRegressionJourneys(mixed $journeys): bool
    {
        return $this->hasListItems($journeys);
    }

    private function hasListItems(mixed $items): bool
    {
        return is_array($items)
            && collect($items)->contains(fn (mixed $item): bool => is_string($item) && trim($item) !== '');
    }
}
