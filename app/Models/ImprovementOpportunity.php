<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ImprovementOpportunity extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'annual_benefit' => 'float',
        'duration_years' => 'integer',
        'pv_of_impact' => 'float',
        'rank' => 'integer',
        'source_attributions' => 'array',
        'superseded_at' => 'datetime',
    ];

    /**
     * @param  Builder<ImprovementOpportunity>  $query
     * @return Builder<ImprovementOpportunity>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /**
     * @return BelongsTo<Client, ImprovementOpportunity>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisFinding, ImprovementOpportunity>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'analysis_finding_id');
    }

    /**
     * @return BelongsTo<PvCalculation, ImprovementOpportunity>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }
}
