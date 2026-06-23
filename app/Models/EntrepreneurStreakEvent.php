<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrepreneurStreakEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'active_day' => 'date',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, EntrepreneurStreakEvent>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }
}
