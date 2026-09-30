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

    /**
     * The retired, combined website scope remains so past acceptances retain
     * their original legal record. New public documents use the two scopes
     * below and are versioned independently.
     */
    public const SCOPE_WEBSITE_TERMS = 'website_terms';

    public const SCOPE_PRIVACY_POLICY = 'privacy_policy';

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
        return match ($scope) {
            self::SCOPE_WEBSITE => 'Terms and Privacy Policy',
            self::SCOPE_WEBSITE_TERMS => 'Terms of Use',
            self::SCOPE_PRIVACY_POLICY => 'Privacy Policy',
            default => 'Terms & Conditions',
        };
    }

    public static function defaultTitle(string $scope): string
    {
        return match ($scope) {
            self::SCOPE_WEBSITE => 'Future Shift Advisory Terms and Privacy Policy',
            self::SCOPE_WEBSITE_TERMS => 'Future Shift Advisory Terms of Use',
            self::SCOPE_PRIVACY_POLICY => 'Future Shift Advisory Privacy Policy',
            default => 'Future Shift Advisory Terms and Conditions',
        };
    }

    public static function isPublicDocumentScope(string $scope): bool
    {
        return in_array($scope, [
            self::SCOPE_WEBSITE,
            self::SCOPE_WEBSITE_TERMS,
            self::SCOPE_PRIVACY_POLICY,
        ], true);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
