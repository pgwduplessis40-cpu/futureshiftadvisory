<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnlyPlanGateRecord;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanGateRun extends Model
{
    use AppendOnlyPlanGateRecord, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['business_plan_id', 'document_kind', 'audience', 'source_hash', 'inputs_hash', 'context_hash', 'configuration_hash', 'required_horizon', 'source_payload', 'result_payload', 'actor_user_id'];

    protected $casts = ['source_payload' => 'array', 'result_payload' => 'array', 'required_horizon' => 'integer'];

    /** @return BelongsTo<BusinessPlan, $this> */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
