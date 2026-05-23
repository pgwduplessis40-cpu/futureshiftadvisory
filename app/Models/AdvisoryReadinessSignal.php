<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdvisoryReadinessSignal extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'surfaced_at' => 'datetime',
        'advisor_notified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, AdvisoryReadinessSignal>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<BusinessPlan, AdvisoryReadinessSignal>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<PlanAssessment, AdvisoryReadinessSignal>
     */
    public function planAssessment(): BelongsTo
    {
        return $this->belongsTo(PlanAssessment::class);
    }
}
