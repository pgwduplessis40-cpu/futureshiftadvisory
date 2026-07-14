<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EntrepreneurPointEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'points' => 'integer',
        'earned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EntrepreneurProfile, EntrepreneurPointEvent>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<EntrepreneurMilestoneAward, EntrepreneurPointEvent>
     */
    public function milestoneAward(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurMilestoneAward::class);
    }
}
