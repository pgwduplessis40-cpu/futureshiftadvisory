<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RatingValidityTest extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'correlation' => 'array',
        'tested_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RatingFramework, RatingValidityTest>
     */
    public function ratingFramework(): BelongsTo
    {
        return $this->belongsTo(RatingFramework::class);
    }
}
