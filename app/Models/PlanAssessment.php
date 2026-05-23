<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanAssessment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'round' => 'integer',
        'ai_scores' => 'array',
        'advisor_scores' => 'array',
        'mentor_notes' => 'array',
        'document_support' => 'array',
        'finalised_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<BusinessPlan, PlanAssessment>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<RatingFramework, PlanAssessment>
     */
    public function ratingFramework(): BelongsTo
    {
        return $this->belongsTo(RatingFramework::class);
    }

    /**
     * @return BelongsTo<PvCalculation, PlanAssessment>
     */
    public function conceptPvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class, 'concept_pv_calculation_id');
    }
}
