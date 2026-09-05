<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanAssessment extends Model
{
    use HasUuids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'business_plan_id',
        'round',
        'rating_framework_id',
        'ai_scores',
        'advisor_scores',
        'mentor_notes',
        'document_support',
        'plan_snapshot',
        'scoring_scope',
        'overall_grade',
        'concept_pv_calculation_id',
        'finalised_at',
        'finalised_by_user_id',
    ];

    protected $casts = [
        'round' => 'integer',
        'ai_scores' => 'array',
        'advisor_scores' => 'array',
        'mentor_notes' => 'array',
        'document_support' => 'array',
        'plan_snapshot' => 'array',
        'scoring_scope' => 'array',
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
