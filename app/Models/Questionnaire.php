<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionnaireSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Questionnaire extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'set' => QuestionnaireSet::class,
        'published_at' => 'datetime',
    ];

    /**
     * @return HasMany<QuestionnaireSection>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(QuestionnaireSection::class)->orderBy('order');
    }

    /**
     * @return HasMany<QuestionnaireResponse>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponse::class);
    }

    /**
     * @param  Builder<Questionnaire>  $query
     * @return Builder<Questionnaire>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * @param  Builder<Questionnaire>  $query
     * @return Builder<Questionnaire>
     */
    public function scopeForSet(Builder $query, QuestionnaireSet|string $set): Builder
    {
        $value = $set instanceof QuestionnaireSet ? $set->value : $set;

        return $query->where('set', $value);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
