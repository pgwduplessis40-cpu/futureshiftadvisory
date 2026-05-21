<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TermsAcceptance extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'expires_at' => 'datetime',
        'reacceptance_notice_queued_at' => 'datetime',
        'signed_pdf_envelope_meta' => 'array',
        'signed_pdf_byte_size' => 'integer',
    ];

    /**
     * @return BelongsTo<User, TermsAcceptance>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TermsVersion, TermsAcceptance>
     */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }

    /**
     * @param  Builder<TermsAcceptance>  $query
     * @return Builder<TermsAcceptance>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('accepted_at')
            ->whereNull('declined_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
