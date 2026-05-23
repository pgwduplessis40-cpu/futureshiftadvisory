<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use App\Models\LearningUpdateDecision;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ApprovalFlow
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(): Collection
    {
        return LearningUpdate::query()
            ->with(['decisions' => fn ($query) => $query->latest('decided_at')])
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
                LearningUpdate::STATUS_APPROVED,
            ])
            ->orderByRaw("case status when 'detected' then 0 when 'staged' then 1 when 'deferred' then 2 else 3 end")
            ->latest('created_at')
            ->get()
            ->map(fn (LearningUpdate $update): array => $this->card($update));
    }

    public function decide(
        LearningUpdate $update,
        string $decision,
        User $actor,
        ?CarbonInterface $effectiveDate = null,
        ?string $reason = null,
    ): LearningUpdateDecision {
        if (! in_array($decision, $this->decisions(), true)) {
            throw new InvalidArgumentException("Unsupported learning update decision [{$decision}].");
        }

        return DB::transaction(function () use ($update, $decision, $actor, $effectiveDate, $reason): LearningUpdateDecision {
            /** @var LearningUpdate $locked */
            $locked = LearningUpdate::query()
                ->whereKey($update->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $decidedAt = now();
            $effectiveAt = $this->effectiveDateFor($decision, $effectiveDate, $decidedAt);
            $status = match ($decision) {
                LearningUpdateDecision::DECISION_APPROVE,
                LearningUpdateDecision::DECISION_APPROVE_MODIFIED_DATE => LearningUpdate::STATUS_APPROVED,
                LearningUpdateDecision::DECISION_DEFER => LearningUpdate::STATUS_DEFERRED,
                LearningUpdateDecision::DECISION_REJECT => LearningUpdate::STATUS_REJECTED,
            };

            $locked->forceFill([
                'status' => $status,
                'effective_date' => $effectiveAt,
                'pre_implementation_notice_at' => $effectiveAt instanceof CarbonInterface ? $decidedAt : null,
                'review_due_at' => $effectiveAt instanceof CarbonInterface ? $effectiveAt->copy()->addDays(30) : null,
                'decided_by_user_id' => $actor->getAuthIdentifier(),
                'decided_at' => $decidedAt,
            ])->save();

            /** @var LearningUpdateDecision $record */
            $record = $locked->decisions()->create([
                'decision' => $decision,
                'effective_date' => $effectiveAt,
                'reason' => $reason,
                'decided_by_user_id' => $actor->getAuthIdentifier(),
                'decided_at' => $decidedAt,
            ]);

            $this->audit->record('learning_update.decided', subject: $locked, actor: $actor, after: [
                'decision' => $decision,
                'status' => $status,
                'effective_date' => $effectiveAt?->toIso8601String(),
                'pre_implementation_notice_at' => $locked->pre_implementation_notice_at?->toIso8601String(),
                'review_due_at' => $locked->review_due_at?->toIso8601String(),
            ]);

            return $record;
        });
    }

    public function assertImplementationAllowed(LearningUpdate $update): void
    {
        if ($update->status !== LearningUpdate::STATUS_APPROVED) {
            throw new RuntimeException('Learning update implementation requires explicit approval.');
        }

        if (! $update->effective_date instanceof CarbonInterface || $update->effective_date->isFuture()) {
            throw new RuntimeException('Learning update implementation is blocked until the approved effective date.');
        }
    }

    /**
     * @return array<int, string>
     */
    public function decisions(): array
    {
        return [
            LearningUpdateDecision::DECISION_APPROVE,
            LearningUpdateDecision::DECISION_APPROVE_MODIFIED_DATE,
            LearningUpdateDecision::DECISION_DEFER,
            LearningUpdateDecision::DECISION_REJECT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(LearningUpdate $update): array
    {
        /** @var EloquentCollection<int, LearningUpdateDecision> $decisions */
        $decisions = $update->decisions;

        return [
            'id' => $update->id,
            'layer_id' => $update->layer_id,
            'source' => $update->source,
            'summary' => $update->summary,
            'proposed_change' => $update->proposed_change,
            'impact_scope' => $update->impact_scope,
            'clients_affected' => $update->clients_affected,
            'magnitude' => $update->magnitude,
            'confidence' => $update->confidence,
            'evidence' => $update->evidence,
            'status' => $update->status,
            'effective_date' => $update->effective_date?->toIso8601String(),
            'pre_implementation_notice_at' => $update->pre_implementation_notice_at?->toIso8601String(),
            'review_due_at' => $update->review_due_at?->toIso8601String(),
            'latest_decision' => $decisions->first() instanceof LearningUpdateDecision
                ? [
                    'decision' => $decisions->first()->decision,
                    'reason' => $decisions->first()->reason,
                    'decided_at' => $decisions->first()->decided_at?->toIso8601String(),
                ]
                : null,
        ];
    }

    private function effectiveDateFor(string $decision, ?CarbonInterface $effectiveDate, CarbonInterface $decidedAt): ?CarbonInterface
    {
        if ($decision === LearningUpdateDecision::DECISION_DEFER || $decision === LearningUpdateDecision::DECISION_REJECT) {
            return null;
        }

        $minimum = $decidedAt->copy()->addDays(7);

        if (! $effectiveDate instanceof CarbonInterface) {
            return $minimum;
        }

        return $effectiveDate->greaterThan($minimum) ? $effectiveDate : $minimum;
    }
}
