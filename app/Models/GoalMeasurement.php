<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoalMeasurement extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'pv_realised' => 'float',
        'observed_at' => 'datetime',
    ];

    /** @return BelongsTo<Goal, GoalMeasurement> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsTo<Client, GoalMeasurement> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<PvCalculation, GoalMeasurement> */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }

    /** @return BelongsTo<User, GoalMeasurement> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
