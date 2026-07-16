<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class IntegrationScope extends Model
{
    use HasUuids;

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    public const DELIVERY_INHOUSE = 'inhouse';

    public const DELIVERY_PARTNER = 'partner';

    public const DELIVERY_LOWCODE = 'lowcode';

    public const DELIVERY_MIXED = 'mixed';

    protected $guarded = [];

    protected $casts = [
        'systems' => 'array',
        'tasks' => 'array',
        'connections' => 'array',
        'partner_cost_estimate' => 'float',
        'partner_margin_percent' => 'float',
        'capture_percent' => 'float',
        'discount_rate_percent' => 'float',
        'computed' => 'array',
        'source_document_ids' => 'array',
        'extracted_rows' => 'array',
        'quoted_fee' => 'float',
        'fsa_hosting_enabled' => 'boolean',
        'flags' => 'array',
    ];

    /**
     * @return BelongsTo<Client, IntegrationScope>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PvCalculation, IntegrationScope>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }

    /**
     * @return BelongsTo<Proposal, IntegrationScope>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<Goal, IntegrationScope>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<BillingAdjustment, IntegrationScope>
     */
    public function scopingCreditAdjustment(): BelongsTo
    {
        return $this->belongsTo(BillingAdjustment::class, 'scoping_credit_adjustment_id');
    }

    /**
     * @return BelongsTo<User, IntegrationScope>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<FeeCalculation>
     */
    public function feeCalculations(): HasMany
    {
        return $this->hasMany(FeeCalculation::class);
    }

    /**
     * @return MorphMany<QuoteSourceExtraction, IntegrationScope>
     */
    public function quoteSourceExtractions(): MorphMany
    {
        return $this->morphMany(QuoteSourceExtraction::class, 'scopeable');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }
}
