<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StrategicPlan extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DEPLOYED = 'deployed';

    protected $guarded = [];

    protected $casts = [
        'sections' => 'array',
        'generated_at' => 'datetime',
        'deployed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, StrategicPlan>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Proposal, StrategicPlan>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<StrategicBudget, StrategicPlan>
     */
    public function strategicBudget(): BelongsTo
    {
        return $this->belongsTo(StrategicBudget::class);
    }

    /**
     * @return HasMany<StrategicPlanMilestone>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(StrategicPlanMilestone::class);
    }
}
