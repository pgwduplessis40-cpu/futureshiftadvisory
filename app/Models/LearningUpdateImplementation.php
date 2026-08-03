<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LearningUpdateImplementation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'implemented_at' => 'datetime',
        'review_due' => 'datetime',
        'before_state' => 'array',
        'after_state' => 'array',
        'review_metrics' => 'array',
        'rolled_back_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearningUpdate, $this>
     */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }

    /**
     * @return HasOne<LearningRollback, $this>
     */
    public function rollback(): HasOne
    {
        return $this->hasOne(LearningRollback::class);
    }
}
