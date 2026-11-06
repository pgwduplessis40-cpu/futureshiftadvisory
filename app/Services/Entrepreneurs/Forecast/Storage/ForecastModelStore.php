<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurForecastModel;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves, loads and selects a plan's version-2 reconstruction.
 *
 * Concurrency follows EntrepreneurBudgetService: a locked read, an expected
 * revision the caller must still hold, and a revision bump only when the
 * stored payload actually changed. A second concurrency story for the same
 * workflow would be a worse answer than reusing this one.
 *
 * Selection is separate from saving on purpose. An advisor can build and
 * review a reconstruction with no effect on any downstream payload; only
 * selecting it makes the plan a version-2 plan. A plan with no selected model
 * is the per-client opt-in default and behaves exactly as it did before this
 * store existed.
 */
final class ForecastModelStore
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function save(BusinessPlan $plan, ForecastModelInputs $inputs, User $actor, ?int $expectedRevision = null): EntrepreneurForecastModel
    {
        return DB::transaction(function () use ($plan, $inputs, $actor, $expectedRevision): EntrepreneurForecastModel {
            $model = EntrepreneurForecastModel::query()
                ->where('business_plan_id', $plan->getKey())
                ->lockForUpdate()
                ->first();
            $this->assertExpectedRevision($model, $expectedRevision);

            if (! $model instanceof EntrepreneurForecastModel) {
                $model = new EntrepreneurForecastModel([
                    'business_plan_id' => $plan->getKey(),
                    'revision' => 1,
                ]);
            }

            $before = $model->exists ? $this->fingerprints($model) : null;
            $model->forceFill([
                'business_plan_id' => $plan->getKey(),
                'engine_version' => ForecastEngineVersion::CURRENT,
                'drivers' => $this->plain($inputs->driversPayload()),
                'actuals' => $this->plain($inputs->actualsPayload()),
                'reserve_policy' => $inputs->reservePolicyPayload() === null
                    ? null
                    : $this->plain($inputs->reservePolicyPayload()->jsonSerialize()),
            ]);

            $after = $this->fingerprints($model);
            if ($before === $after) {
                $model->syncOriginal();

                return $model->refresh();
            }

            $changed = $this->changedSections($before, $after);
            $model->revision = $model->exists ? $model->revision + 1 : 1;
            $model->save();

            // The payloads themselves are large and are already recoverable
            // from the row; the audit trail records which sections moved and
            // a hash a reviewer can verify them against. The fingerprints are
            // re-read after the save so the event names the revision it
            // produced, not the one it replaced.
            $this->audit->record('entrepreneur.forecast_model_saved', subject: $model, actor: $actor, before: $before, after: [
                ...$this->fingerprints($model),
                'business_plan_id' => $plan->getKey(),
                'changed_sections' => $changed,
                'engine_components' => ForecastEngineVersion::components(),
            ]);

            return $model->refresh();
        });
    }

    /** Makes this reconstruction the plan's financial model. */
    public function select(EntrepreneurForecastModel $model, User $actor): EntrepreneurForecastModel
    {
        if ($model->isSelected()) {
            return $model;
        }

        $model->forceFill(['selected_at' => now(), 'selected_by_user_id' => $actor->getKey()])->save();
        $this->audit->record('entrepreneur.forecast_model_selected', subject: $model, actor: $actor, after: [
            'business_plan_id' => $model->business_plan_id,
            'engine_version' => $model->engine_version,
            'revision' => $model->revision,
        ]);

        return $model->refresh();
    }

    /** Returns the plan to the legacy model. Recorded, never silent. */
    public function deselect(EntrepreneurForecastModel $model, User $actor): EntrepreneurForecastModel
    {
        if (! $model->isSelected()) {
            return $model;
        }

        $previous = ['selected_at' => $model->selected_at?->toIso8601String(), 'selected_by_user_id' => $model->selected_by_user_id];
        $model->forceFill(['selected_at' => null, 'selected_by_user_id' => null])->save();
        $this->audit->record('entrepreneur.forecast_model_deselected', subject: $model, actor: $actor, before: $previous, after: [
            'business_plan_id' => $model->business_plan_id,
            'revision' => $model->revision,
        ]);

        return $model->refresh();
    }

    public function forPlan(BusinessPlan $plan): ?EntrepreneurForecastModel
    {
        return EntrepreneurForecastModel::query()->where('business_plan_id', $plan->getKey())->first();
    }

    /** The selected model only. A saved but unselected model is not one. */
    public function selectedForPlan(BusinessPlan $plan): ?EntrepreneurForecastModel
    {
        $model = $this->forPlan($plan);

        return $model?->isSelected() === true ? $model : null;
    }

    /** Reads a stored row back into engine value objects, or raises. */
    public function inputs(EntrepreneurForecastModel $model): ForecastModelInputs
    {
        return ForecastModelCodec::decode(
            (string) $model->engine_version,
            $model->drivers,
            $model->actuals,
            $model->reserve_policy,
        );
    }

    private function assertExpectedRevision(?EntrepreneurForecastModel $model, ?int $expectedRevision): void
    {
        if ($expectedRevision === null) {
            return;
        }

        $actual = $model instanceof EntrepreneurForecastModel ? $model->revision : 0;
        if ($expectedRevision === $actual) {
            return;
        }

        throw ValidationException::withMessages([
            'revision' => 'This forecast model changed in another session. Reload it before saving so newer figures are not overwritten.',
        ]);
    }

    /**
     * @return array{engine_version:string,revision:int,drivers_hash:string,actuals_hash:string,reserve_policy_hash:string}
     */
    private function fingerprints(EntrepreneurForecastModel $model): array
    {
        return [
            'engine_version' => (string) $model->engine_version,
            'revision' => (int) $model->revision,
            'drivers_hash' => $this->hash($model->drivers),
            'actuals_hash' => $this->hash($model->actuals),
            'reserve_policy_hash' => $this->hash($model->reserve_policy),
        ];
    }

    /**
     * @param  array{engine_version:string,revision:int,drivers_hash:string,actuals_hash:string,reserve_policy_hash:string}|null  $before
     * @param  array{engine_version:string,revision:int,drivers_hash:string,actuals_hash:string,reserve_policy_hash:string}  $after
     * @return list<string>
     */
    private function changedSections(?array $before, array $after): array
    {
        $changed = [];
        foreach (['drivers', 'actuals', 'reserve_policy'] as $section) {
            if ($before === null || $before[$section.'_hash'] !== $after[$section.'_hash']) {
                $changed[] = $section;
            }
        }

        return $changed;
    }

    private function hash(mixed $payload): string
    {
        if ($payload === null) {
            return hash('sha256', 'null');
        }

        return hash('sha256', (string) json_encode($this->canonical($payload), JSON_UNESCAPED_SLASHES));
    }

    /** Key order must not change a hash, or every reload would look edited. */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }

    /**
     * The engine's value objects serialize themselves; this turns the nested
     * result into the plain arrays the jsonb column stores and the codec
     * reads, so what is written is exactly what comes back.
     */
    private function plain(mixed $payload): mixed
    {
        return json_decode((string) json_encode($payload, JSON_UNESCAPED_SLASHES), true);
    }
}
