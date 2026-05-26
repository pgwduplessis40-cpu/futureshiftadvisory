<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Template extends Model
{
    use HasUuids;

    public const CATEGORY_REPORT = 'report';

    public const CATEGORY_PROPOSAL = 'proposal';

    public const CATEGORY_EMAIL = 'email';

    public const CATEGORY_PLAN_SECTION = 'plan_section';

    public const CATEGORY_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected $casts = [
        'structure' => 'array',
        'version' => 'integer',
    ];

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_REPORT,
            self::CATEGORY_PROPOSAL,
            self::CATEGORY_EMAIL,
            self::CATEGORY_PLAN_SECTION,
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
            self::CATEGORY_REPORT => 'Report',
            self::CATEGORY_PROPOSAL => 'Proposal',
            self::CATEGORY_EMAIL => 'Email',
            self::CATEGORY_PLAN_SECTION => 'Plan section',
            default => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function libraryStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeLibrary(Builder $query): Builder
    {
        return $query->whereIn('status', self::libraryStatuses());
    }

    /**
     * @return BelongsTo<User, Template>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<LearningUpdateImplementation, Template>
     */
    public function learningUpdateImplementation(): BelongsTo
    {
        return $this->belongsTo(LearningUpdateImplementation::class);
    }
}
