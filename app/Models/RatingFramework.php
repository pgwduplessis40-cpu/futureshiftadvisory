<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RatingFramework extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const FOUNDING_CRITERIA = [
        1 => 'Type of business',
        2 => 'Location',
        3 => 'Means of doing business',
        4 => 'Discuss the industry',
        5 => 'What sets the business apart',
        6 => 'Describe unique success factors',
        7 => 'Mission and Vision statement',
        8 => 'Intellectual property',
        9 => 'Goals and objectives',
        10 => 'Culture',
        11 => 'Legal Environment',
        12 => 'Budget',
    ];

    public const DEFAULT_GRADE_BANDS = [
        'exceptional' => ['min' => 90, 'label' => 'Exceptional'],
        'strong' => ['min' => 75, 'label' => 'Strong'],
        'developing' => ['min' => 60, 'label' => 'Developing'],
        'needs_work' => ['min' => 0, 'label' => 'Needs Work'],
    ];

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'production_ready' => 'boolean',
        'grade_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * @return HasMany<RatingCriterion>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(RatingCriterion::class)->orderBy('number');
    }

    /**
     * @return HasMany<RatingValidityTest>
     */
    public function validityTests(): HasMany
    {
        return $this->hasMany(RatingValidityTest::class);
    }

    /**
     * @return BelongsTo<RatingFramework, RatingFramework>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_framework_id');
    }

    public function gradeFor(float $percentage): string
    {
        $bands = collect($this->grade_bands ?: self::DEFAULT_GRADE_BANDS)
            ->sortByDesc(fn (array $band): float => (float) ($band['min'] ?? 0));

        foreach ($bands as $key => $band) {
            if ($percentage >= (float) ($band['min'] ?? 0)) {
                return (string) $key;
            }
        }

        return 'needs_work';
    }

    /**
     * @return array{production_ready:bool,placeholder_criteria:int,message:string}
     */
    public function readinessStatus(): array
    {
        $placeholderCount = $this->criteria()->where('is_placeholder', true)->count();

        return [
            'production_ready' => $this->production_ready && $placeholderCount === 0,
            'placeholder_criteria' => $placeholderCount,
            'message' => $this->production_ready && $placeholderCount === 0
                ? 'Rating framework is production-ready.'
                : 'Rating framework is not production-ready until owner-set weights and descriptors are confirmed.',
        ];
    }
}
