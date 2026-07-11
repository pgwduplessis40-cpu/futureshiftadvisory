<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class BoardPost extends Model
{
    use HasUuids;

    public const TYPE_MESSAGE = 'message';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_IMAGE = 'image';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const FEATURE_SOURCE_MANUAL = 'manual';

    public const FEATURE_SOURCE_ROTATION = 'rotation';

    public const FEATURE_SOURCE_FALLBACK = 'fallback';

    public const TYPES = [self::TYPE_MESSAGE, self::TYPE_QUOTE, self::TYPE_IMAGE];

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    protected $guarded = [];

    protected $casts = [
        'pinned' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'featured_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, BoardPost>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Document, BoardPost>
     */
    public function imageDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'image_document_id');
    }

    /**
     * @return BelongsToMany<InspirationRotationSchedule, BoardPost>
     */
    public function rotationSchedules(): BelongsToMany
    {
        return $this->belongsToMany(InspirationRotationSchedule::class, 'inspiration_rotation_schedule_posts')
            ->withPivot(['position', 'scheduled_at', 'featured_at']);
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isReleased(): bool
    {
        return $this->isPublished()
            && ($this->scheduled_at === null || $this->scheduled_at->lte(now()));
    }

    /**
     * @param  Builder<BoardPost>  $query
     * @return Builder<BoardPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<BoardPost>  $query
     * @return Builder<BoardPost>
     */
    public function scopeReleased(Builder $query): Builder
    {
        return $query
            ->published()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }
}
