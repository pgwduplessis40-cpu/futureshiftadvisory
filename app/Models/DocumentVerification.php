<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentVerification extends Model
{
    use HasUuids;

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_VERIFIED = 'verified';

    public const OUTCOME_ADVISORY_FLAG = 'advisory_flag';

    public const OUTCOME_ACCURACY_DISCREPANCY = 'accuracy_discrepancy';

    public const OUTCOME_VERIFICATION_ERROR = 'verification_error';

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'float',
        'source_payload' => 'array',
        'ai_payload' => 'array',
        'verified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Document, DocumentVerification>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Client, DocumentVerification>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<EntrepreneurProfile, DocumentVerification>
     */
    public function entrepreneurProfile(): BelongsTo
    {
        return $this->belongsTo(EntrepreneurProfile::class);
    }

    /**
     * @return BelongsTo<PlanSection, DocumentVerification>
     */
    public function planSection(): BelongsTo
    {
        return $this->belongsTo(PlanSection::class);
    }

    /**
     * @return BelongsTo<QuestionnaireResponse, DocumentVerification>
     */
    public function questionnaireResponse(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireResponse::class);
    }

    /**
     * @return BelongsTo<QuestionnaireAnswer, DocumentVerification>
     */
    public function questionnaireAnswer(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireAnswer::class);
    }

    /**
     * @return BelongsTo<QuestionnaireQuestion, DocumentVerification>
     */
    public function questionnaireQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireQuestion::class);
    }

    /**
     * @return BelongsTo<User, DocumentVerification>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * @param  Builder<DocumentVerification>  $query
     * @return Builder<DocumentVerification>
     */
    public function scopeOutstandingFlags(Builder $query): Builder
    {
        return $query
            ->whereNull('resolved_at')
            ->whereIn('outcome', [
                self::OUTCOME_ADVISORY_FLAG,
                self::OUTCOME_ACCURACY_DISCREPANCY,
            ]);
    }

    public function isBlockingAnalysis(): bool
    {
        return $this->resolved_at === null
            && in_array($this->outcome, [
                self::OUTCOME_ADVISORY_FLAG,
                self::OUTCOME_ACCURACY_DISCREPANCY,
            ], true);
    }

    public function clientFacingExplanation(): string
    {
        if (is_string($this->client_explanation) && trim($this->client_explanation) !== '') {
            return $this->client_explanation;
        }

        return match ($this->outcome) {
            self::OUTCOME_VERIFIED => 'This document supports the attached claim.',
            self::OUTCOME_ADVISORY_FLAG => 'An advisor is reviewing this document before it is used in analysis.',
            self::OUTCOME_ACCURACY_DISCREPANCY => 'This document appears to conflict with the attached claim, so related analysis is paused.',
            self::OUTCOME_VERIFICATION_ERROR => 'Automated verification could not be completed yet.',
            default => 'Verification is in progress.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function outcomes(): array
    {
        return [
            self::OUTCOME_PENDING,
            self::OUTCOME_VERIFIED,
            self::OUTCOME_ADVISORY_FLAG,
            self::OUTCOME_ACCURACY_DISCREPANCY,
            self::OUTCOME_VERIFICATION_ERROR,
        ];
    }
}
