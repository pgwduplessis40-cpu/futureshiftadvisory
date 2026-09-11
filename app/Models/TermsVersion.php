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

    public const SCOPE_PROPOSAL = 'proposal';

    public const SCOPE_WEBSITE = 'website';

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

    /**
     * @param  Builder<TermsVersion>  $query
     * @return Builder<TermsVersion>
     */
    public function scopeForDocument(Builder $query, string $scope): Builder
    {
        return $query->where('document_scope', $scope);
    }

    public static function documentLabel(string $scope): string
    {
        return $scope === self::SCOPE_WEBSITE
            ? 'Terms and Privacy Policy'
            : 'Terms & Conditions';
    }

    public static function defaultTitle(string $scope): string
    {
        return $scope === self::SCOPE_WEBSITE
            ? 'Future Shift Advisory Terms and Privacy Policy'
            : 'Future Shift Advisory Terms and Conditions';
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
