<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlanPhase extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETE = 'complete';

    protected $guarded = [];

    protected $casts = [
        'depends_on' => 'array',
    ];

    /**
     * @return BelongsTo<BusinessPlan, PlanPhase>
     */
    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    /**
     * @return HasMany<PlanSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PlanSection::class);
    }
}
