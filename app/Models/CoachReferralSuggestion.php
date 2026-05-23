<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CoachReferralSuggestion extends Model
{
    use HasUuids;

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'surfaced_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CoachingSignal, CoachReferralSuggestion>
     */
    public function coachingSignal(): BelongsTo
    {
        return $this->belongsTo(CoachingSignal::class);
    }

    /**
     * @return BelongsTo<Client, CoachReferralSuggestion>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, CoachReferralSuggestion>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
