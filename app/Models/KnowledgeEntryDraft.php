<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeEntryDraft extends Model
{
    use HasUuids;

    public const STATE_PENDING = 'pending';

    public const STATE_ACCEPTED = 'accepted';

    public const STATE_DISCARDED = 'discarded';

    public const SOURCE_OFFBOARDING_RECORD = 'offboarding_record';

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'source_attribution' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function states(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_ACCEPTED,
            self::STATE_DISCARDED,
        ];
    }

    /**
     * @param  Builder<KnowledgeEntryDraft>  $query
     * @return Builder<KnowledgeEntryDraft>
     */
    public function scopeForAuthor(Builder $query, User $user): Builder
    {
        return $query->where('author_user_id', $user->getKey());
    }

    /**
     * @param  Builder<KnowledgeEntryDraft>  $query
     * @return Builder<KnowledgeEntryDraft>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', self::STATE_PENDING);
    }

    /**
     * @return BelongsTo<User, KnowledgeEntryDraft>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<Client, KnowledgeEntryDraft>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<KnowledgeEntry, KnowledgeEntryDraft>
     */
    public function acceptedEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'accepted_entry_id');
    }
}
