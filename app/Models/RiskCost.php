<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RiskCost extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'financial_impact' => 'float',
        'probability' => 'float',
        'duration_years' => 'integer',
        'statutory_penalty_range' => 'array',
        'applied_impact' => 'float',
        'annual_expected_cost' => 'float',
        'pv_of_cost' => 'float',
        'rank' => 'integer',
        'cash_flow_years' => 'integer',
        'source_attributions' => 'array',
        'superseded_at' => 'datetime',
    ];

    /**
     * @param  Builder<RiskCost>  $query
     * @return Builder<RiskCost>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /**
     * @return BelongsTo<Client, RiskCost>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<AnalysisFinding, RiskCost>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AnalysisFinding::class, 'analysis_finding_id');
    }

    /**
     * @return BelongsTo<PvCalculation, RiskCost>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }
}
