<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class InspirationRotationSchedule extends Model
{
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cadence_days' => 'integer',
    ];

    /**
     * @return BelongsTo<User, InspirationRotationSchedule>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsToMany<BoardPost, InspirationRotationSchedule>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BoardPost::class, 'inspiration_rotation_schedule_posts')
            ->withPivot(['position', 'scheduled_at', 'featured_at'])
            ->orderBy('inspiration_rotation_schedule_posts.position');
    }

    public function isActiveAt(CarbonInterface $at): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lte($at)
            && $this->ends_at->gte($at);
    }
}
