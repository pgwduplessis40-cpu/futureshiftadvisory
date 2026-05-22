<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Milestone extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BLOCKED = 'blocked';

    protected $guarded = [];

    protected $casts = [
        'pv_of_impact' => 'float',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Goal, Milestone>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<Client, Milestone>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PvCalculation, Milestone>
     */
    public function pvOfImpactCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class, 'pv_of_impact_calculation_id');
    }

    /**
     * @return HasMany<MilestoneAction>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(MilestoneAction::class);
    }

    /**
     * @return HasMany<ProofOfCompletion>
     */
    public function proofOfCompletion(): HasMany
    {
        return $this->hasMany(ProofOfCompletion::class);
    }
}
