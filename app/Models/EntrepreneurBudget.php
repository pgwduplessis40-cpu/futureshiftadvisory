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

    protected $guarded = [];

    protected $casts = [
        'expected_runway_months' => 'integer',
        'launch_costs' => 'array',
        'monthly_fixed_costs' => 'array',
        'revenue_forecast' => 'array',
        'funding_sources' => 'array',
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
