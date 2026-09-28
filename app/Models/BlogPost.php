<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BlogPost extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'description',
        'body',
        'status',
        'published_title',
        'published_description',
        'published_body',
        'published_at',
        'published_revision_at',
        'scheduled_title',
        'scheduled_description',
        'scheduled_body',
        'scheduled_at',
        'author_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'published_revision_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePubliclyPublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_title')
            ->whereNotNull('published_description')
            ->whereNotNull('published_body')
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function hasBeenPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isSlugLocked(): bool
    {
        return $this->hasBeenPublished() || $this->isScheduled();
    }
}
