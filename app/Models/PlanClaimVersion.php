<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnlyPlanGateRecord;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanClaimVersion extends Model
{
    use AppendOnlyPlanGateRecord, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['business_plan_id', 'claim_key', 'revision', 'state', 'source_hash', 'fact', 'reason', 'actor_user_id'];

    protected $casts = ['revision' => 'integer', 'fact' => 'array'];

    /** @return BelongsTo<BusinessPlan, $this> */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
