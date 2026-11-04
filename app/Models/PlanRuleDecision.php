<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnlyPlanGateRecord;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanRuleDecision extends Model
{
    use AppendOnlyPlanGateRecord, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['business_plan_id', 'plan_gate_run_id', 'rule_id', 'subcheck_id', 'finding_hash', 'dependency_hash', 'configuration_hash', 'action', 'supersedes_id', 'reason', 'actor_user_id'];

    /** @return BelongsTo<BusinessPlan, $this> */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
