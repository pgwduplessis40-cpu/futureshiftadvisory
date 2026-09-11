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
            if ($status === LearningRecommendation::STATUS_IN_DEVELOPMENT && $developmentReference === null) {
                throw ValidationException::withMessages(['development_reference' => 'Record the development issue, pull request, or commit before marking this recommendation in development.']);
            }
            if ($status === LearningRecommendation::STATUS_IN_DEVELOPMENT && $deliveryOwner === null) {
                throw ValidationException::withMessages(['delivery_owner' => 'Assign a delivery owner before starting development.']);
            }
            if ($status === LearningRecommendation::STATUS_IN_DEVELOPMENT && $deliveryTarget === null) {
                throw ValidationException::withMessages(['delivery_target' => 'Record the intended delivery target before starting development.']);
            }
            if ($status === LearningRecommendation::STATUS_IN_DEVELOPMENT && ! $this->hasListItems($baselineMetrics)) {
                throw ValidationException::withMessages(['baseline_metrics' => 'Record at least one baseline metric before starting development.']);
            }
            if ($status === LearningRecommendation::STATUS_IN_DEVELOPMENT && $rollbackPlan === null) {
                throw ValidationException::withMessages(['rollback_plan' => 'Record a rollback plan before starting development.']);
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

    public function developerBrief(?CarbonInterface $generatedAt = null): string
    {
        $generatedAt ??= now();
        $recommendations = $this->activeRecommendations();
        $lines = [
            '# Future Shift Advisory developer delivery brief',
            '',
            'Generated: '.$generatedAt->toIso8601String(),
            'This briefing contains only recommendations explicitly approved for development or already in the delivery path. It does not authorise product changes without normal review, tests, release gates, verification, and rollback evidence.',
            '',
        ];

        $deliveryItems = $recommendations->filter(fn (LearningRecommendation $item): bool => $item->status !== LearningRecommendation::STATUS_DRAFT);
        if ($deliveryItems->isEmpty()) {
            return implode("\n", [...$lines, 'No approved recommendations are currently waiting for development.', '']);
        }

        foreach ($deliveryItems as $item) {
            $lines[] = '## '.$item->title;
            $lines[] = '';
            $lines[] = '- Status: '.$item->status;
            $lines[] = '- Learning ID: '.$item->learning_update_id;
            $lines[] = '- Delivery owner: '.($item->delivery_owner ?: 'To be assigned before development starts.');
            $lines[] = '- Delivery target: '.($item->delivery_target ?: 'To be set before development starts.');
            $lines[] = '';
            $lines[] = '### Decision context';
            $lines[] = '';
            $lines[] = '- Failure / shortfall: '.$item->failure_shortfall;
            $lines[] = '- Impact: '.$item->impact;
            $lines[] = '- Area of impact: '.$item->impact_area;
            $lines[] = '- Recommendation: '.$item->recommendation;
            $lines[] = '- Expected impact: '.$item->recommendation_impact;
            $lines[] = '';
            $lines[] = '### Delivery and assurance';
            $lines[] = '';
            $lines[] = '- Baseline metrics: '.($this->joined($item->baseline_metrics) ?: 'To be recorded before development starts.');
            $lines[] = '- Acceptance criteria: '.($this->joined($item->acceptance_criteria) ?: 'To be added before development starts.');
            $lines[] = '- Regression journeys: '.($this->joined($item->regression_journeys) ?: 'To be added before release.');
            $lines[] = '- Rollback plan: '.($item->rollback_plan ?: 'To be recorded before development starts.');
            $lines[] = '- Development reference: '.($item->development_reference ?: 'Not started.');
            $lines[] = '- Release reference: '.($item->release_reference ?: 'Not released.');
            $lines[] = '- Verification evidence: '.($item->verification_notes ?: 'Not yet verified.');
            $lines[] = '- Review due: '.($item->review_due_at?->toDateString() ?: 'Set when released.');
            $lines[] = '';
        }

        return implode("\n", $lines);
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
            LearningRecommendation::STATUS_APPROVED, LearningRecommendation::STATUS_BLOCKED => [LearningRecommendation::STATUS_IN_DEVELOPMENT],
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

    /** @param array<mixed>|null $items */
    private function joined(?array $items): string
    {
        return collect($items)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->join('; ');
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
