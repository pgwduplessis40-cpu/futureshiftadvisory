<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeEntry extends Model
{
    use HasUuids;

    public const CATEGORY_METHODOLOGY = 'methodology';

    public const CATEGORY_CLIENT_PATTERN = 'client_pattern';

    public const CATEGORY_PRICING = 'pricing';

    public const CATEGORY_RISK = 'risk';

    public const CATEGORY_TEMPLATE_NOTE = 'template_note';

    public const CATEGORY_OTHER = 'other';

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_METHODOLOGY,
            self::CATEGORY_CLIENT_PATTERN,
            self::CATEGORY_PRICING,
            self::CATEGORY_RISK,
            self::CATEGORY_TEMPLATE_NOTE,
            self::CATEGORY_OTHER,
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function categoryOptions(): array
    {
        return array_map(
            static fn (string $category): array => [
                'value' => $category,
                'label' => self::categoryLabel($category),
            ],
            self::categories(),
        );
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_METHODOLOGY => 'Methodology',
            self::CATEGORY_CLIENT_PATTERN => 'Client pattern',
            self::CATEGORY_PRICING => 'Pricing',
            self::CATEGORY_RISK => 'Risk',
            self::CATEGORY_TEMPLATE_NOTE => 'Template note',
            default => 'Other',
        };
    }

    /**
     * @param  Builder<KnowledgeEntry>  $query
     * @return Builder<KnowledgeEntry>
     */
    public function scopeForAuthor(Builder $query, User $user): Builder
    {
        return $query->where('author_user_id', $user->getKey());
    }

    /**
     * @return BelongsTo<User, KnowledgeEntry>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<Client, KnowledgeEntry>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
