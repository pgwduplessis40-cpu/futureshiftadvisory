<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StrategicPlanMilestone extends Model
{
    use HasUuids;

    public const OWNER_CLIENT = 'client';

    public const OWNER_ADVISOR = 'advisor';

    public const OWNER_JOINT = 'joint';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BLOCKED = 'blocked';

    protected $guarded = [];

    protected $casts = [
        'due_offset_days' => 'integer',
        'due_date' => 'date',
        'progress_percent' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StrategicPlan, StrategicPlanMilestone>
     */
    public function strategicPlan(): BelongsTo
    {
        return $this->belongsTo(StrategicPlan::class);
    }

    /**
     * @return BelongsTo<Client, StrategicPlanMilestone>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
