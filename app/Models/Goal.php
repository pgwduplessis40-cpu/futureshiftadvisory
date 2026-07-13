<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Goal extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ACHIEVED = 'achieved';

    public const STATUS_ABANDONED = 'abandoned';

    protected $guarded = [];

    protected $casts = [
        'pv_target' => 'float',
        'target_date' => 'date',
        'target_growth_percent' => 'float',
        'achieved_at' => 'datetime',
        'pv_remeasurement_failed_at' => 'datetime',
        'pv_remeasurement_next_retry_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, Goal>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PvCalculation, Goal>
     */
    public function pvTargetCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class, 'pv_target_calculation_id');
    }

    /**
     * @return BelongsTo<BusinessValuation, Goal>
     */
    public function baselineBusinessValuation(): BelongsTo
    {
        return $this->belongsTo(BusinessValuation::class, 'baseline_business_valuation_id');
    }

    /**
     * @return BelongsTo<BusinessValuation, Goal>
     */
    public function latestBusinessValuation(): BelongsTo
    {
        return $this->belongsTo(BusinessValuation::class, 'latest_business_valuation_id');
    }

    /**
     * @return BelongsTo<User, Goal>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, Goal>
     */
    public function achievedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'achieved_by_user_id');
    }

    /**
     * @return HasMany<Milestone>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /** @return HasMany<GoalMeasurement> */
    public function measurements(): HasMany
    {
        return $this->hasMany(GoalMeasurement::class);
    }
}
