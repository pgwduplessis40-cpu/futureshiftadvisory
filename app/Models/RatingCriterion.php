<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RatingCriterion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'number' => 'integer',
        'weight' => 'float',
        'descriptors' => 'array',
        'industry_variants' => 'array',
        'is_placeholder' => 'boolean',
    ];

    /**
     * @return BelongsTo<RatingFramework, RatingCriterion>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(RatingFramework::class, 'rating_framework_id');
    }
}
