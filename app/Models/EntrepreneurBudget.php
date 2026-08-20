<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrepreneurBudget extends Model
{
    use HasUuids;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_plan_id',
        'expected_runway_months',
        'forecast_years',
        'status',
        'assumptions',
        'launch_costs',
        'monthly_fixed_costs',
        'future_costs',
        'revenue_forecast',
        'funding_sources',
        'funding_scenarios',
        'computed',
        'flags',
        'advisor_line_nudge_seen_at',
    ];

    protected $casts = [
        'expected_runway_months' => 'integer',
        'forecast_years' => 'integer',
        'assumptions' => 'array',
        'launch_costs' => 'array',
        'monthly_fixed_costs' => 'array',
        'future_costs' => 'array',
        'revenue_forecast' => 'array',
        'funding_sources' => 'array',
        'funding_scenarios' => 'array',
        'computed' => 'array',
        'flags' => 'array',
        'advisor_line_nudge_seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<BusinessPlan, EntrepreneurBudget>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
