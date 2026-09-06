<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearningRecommendation extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_DEVELOPMENT = 'in_development';

    public const STATUS_RELEASED = 'released';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'learning_update_id',
        'title',
        'failure_shortfall',
        'impact',
        'impact_area',
        'recommendation',
        'recommendation_impact',
        'acceptance_criteria',
        'regression_journeys',
        'evidence',
        'status',
        'approved_by_user_id',
        'approved_at',
        'development_reference',
        'release_reference',
        'released_at',
        'verified_by_user_id',
        'verified_at',
        'verification_notes',
        'review_due_at',
        'rolled_back_at',
    ];

    protected $casts = [
        'acceptance_criteria' => 'array',
        'regression_journeys' => 'array',
        'evidence' => 'array',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'verified_at' => 'datetime',
        'review_due_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningUpdate, $this> */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** @return list<string> */
    public static function deliveryStatuses(): array
    {
        return [
            self::STATUS_IN_DEVELOPMENT,
            self::STATUS_RELEASED,
            self::STATUS_VERIFIED,
            self::STATUS_BLOCKED,
            self::STATUS_ROLLED_BACK,
        ];
    }
}
