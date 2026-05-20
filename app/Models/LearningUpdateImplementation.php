<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearningUpdateImplementation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'implemented_at' => 'datetime',
        'review_due' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearningUpdate, $this>
     */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }
}
