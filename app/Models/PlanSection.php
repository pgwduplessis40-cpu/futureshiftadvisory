<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlanSection extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETE = 'complete';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<BusinessPlan, PlanSection>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<PlanPhase, PlanSection>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(PlanPhase::class, 'plan_phase_id');
    }

    /**
     * @return BelongsTo<AnalysisFinding, PlanSection>
     */
    public function sourceFinding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'source_analysis_finding_id');
    }
}
