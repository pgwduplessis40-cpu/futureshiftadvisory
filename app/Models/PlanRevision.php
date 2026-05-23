<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanRevision extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'round' => 'integer',
        'submitted_at' => 'datetime',
        'progress_comparison' => 'array',
    ];

    /**
     * @return BelongsTo<BusinessPlan, PlanRevision>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<User, PlanRevision>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
