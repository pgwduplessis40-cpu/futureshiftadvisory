<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TermsVersion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'material' => 'boolean',
        'published_at' => 'datetime',
        'notice_period_days' => 'integer',
        'source_file' => 'array',
    ];

    /**
     * @return HasMany<TermsClause>
     */
    public function clauses(): HasMany
    {
        return $this->hasMany(TermsClause::class)->orderBy('clause_number');
    }

    /**
     * @return HasMany<TermsAcceptance>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    /**
     * @param  Builder<TermsVersion>  $query
     * @return Builder<TermsVersion>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * @param  Builder<TermsVersion>  $query
     * @return Builder<TermsVersion>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
