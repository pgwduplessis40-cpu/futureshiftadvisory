<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnlyPlanGateRecord;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PlanRuleConfigVersion extends Model
{
    use AppendOnlyPlanGateRecord, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['configuration_hash', 'configuration', 'reason', 'actor_user_id'];

    protected $casts = ['configuration' => 'array'];
}
