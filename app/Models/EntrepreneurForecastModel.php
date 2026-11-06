<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stored version-2 driver reconstruction for one business plan.
 *
 * This sits beside EntrepreneurBudget rather than replacing it. A plan with
 * no selected model keeps the legacy budget arithmetic and the legacy gate
 * verdict unchanged, which is what per-client opt-in means here.
 */
final class EntrepreneurForecastModel extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_plan_id',
        'engine_version',
        'revision',
        'drivers',
        'actuals',
        'reserve_policy',
        'selected_at',
        'selected_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'revision' => 'integer',
        'drivers' => 'array',
        'actuals' => 'array',
        'reserve_policy' => 'array',
        'selected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<BusinessPlan, $this>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    /** A saved model only drives downstream payloads once it is selected. */
    public function isSelected(): bool
    {
        return $this->selected_at !== null;
    }
}
