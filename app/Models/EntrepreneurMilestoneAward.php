<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrepreneurMilestoneAward extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'evidence_snapshot' => 'array',
        'earned_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, EntrepreneurMilestoneAward>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }
}
