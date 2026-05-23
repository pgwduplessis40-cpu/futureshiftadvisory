<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningRollback;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Rollback
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function rollback(LearningUpdateImplementation $implementation, string $reason, User $actor): LearningRollback
    {
        return DB::transaction(function () use ($implementation, $reason, $actor): LearningRollback {
            /** @var LearningUpdateImplementation $locked */
            $locked = LearningUpdateImplementation::query()
                ->with(['learningUpdate', 'rollback'])
                ->whereKey($implementation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->rollback instanceof LearningRollback) {
                return $locked->rollback;
            }

            $update = $locked->learningUpdate;
            if (! $update instanceof LearningUpdate) {
                throw new RuntimeException('Learning implementation cannot be rolled back without its update.');
            }

            $rolledBackAt = now();
            $restoredState = $this->restorePriorState($locked);

            /** @var LearningRollback $rollback */
            $rollback = LearningRollback::query()->create([
                'learning_update_id' => $update->getKey(),
                'learning_update_implementation_id' => $locked->getKey(),
                'reason' => $reason,
                'rolled_back_by_user_id' => $actor->getAuthIdentifier(),
                'rolled_back_at' => $rolledBackAt,
                'restored_state' => $restoredState,
            ]);

            $locked->forceFill(['rolled_back_at' => $rolledBackAt])->save();
            $update->forceFill([
                'status' => LearningUpdate::STATUS_ROLLED_BACK,
                'rollback_id' => $rollback->getKey(),
            ])->save();

            $this->audit->record('learning_update.rolled_back', subject: $update, actor: $actor, before: [
                'implementation_id' => $locked->getKey(),
                'after_state' => $locked->after_state,
            ], after: [
                'rollback_id' => $rollback->getKey(),
                'implementation_id' => $locked->getKey(),
                'restored_state' => $restoredState,
                'rolled_back_at' => $rolledBackAt->toIso8601String(),
            ]);

            return $rollback;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function restorePriorState(LearningUpdateImplementation $implementation): array
    {
        $beforeState = is_array($implementation->before_state) ? $implementation->before_state : [];
        $targetType = $implementation->target_type;
        $targetId = $implementation->target_id;

        $restored = [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'attributes' => $beforeState,
            'restored' => false,
        ];

        if (! is_string($targetType) || $targetType === '' || ! is_string($targetId) || $targetId === '' || $beforeState === []) {
            return $restored;
        }

        $modelClass = Relation::getMorphedModel($targetType) ?? $targetType;
        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return $restored;
        }

        /** @var Model|null $model */
        $model = $modelClass::query()->whereKey($targetId)->first();
        if (! $model instanceof Model) {
            return $restored;
        }

        $model->forceFill($beforeState)->save();

        $restored['restored'] = true;

        return $restored;
    }
}
