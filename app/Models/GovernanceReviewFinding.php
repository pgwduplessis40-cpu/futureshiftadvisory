<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FindingSeverity;
use App\Services\Ai\Contracts\Uncertainty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GovernanceReviewFinding extends Model
{
    use HasUuids;

    public const STATUS_PENDING_ADVISOR_REVIEW = 'pending_advisor_review';

    public const STATUS_REVIEWED = 'reviewed';

    protected $guarded = [];

    protected $casts = [
        'severity' => FindingSeverity::class,
        'criteria' => 'array',
        'evidence' => 'array',
        'attributions' => 'array',
        'uncertainty' => Uncertainty::class,
        'ai_payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, GovernanceReviewFinding>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<NpoEngagement, GovernanceReviewFinding>
     */
    public function npoEngagement(): BelongsTo
    {
        return $this->belongsTo(NpoEngagement::class);
    }

    /**
     * @return BelongsTo<User, GovernanceReviewFinding>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isReviewed(): bool
    {
        return $this->status === self::STATUS_REVIEWED && $this->reviewed_at !== null;
    }
}
