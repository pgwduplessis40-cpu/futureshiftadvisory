<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DdValuation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'source_to_nzd_rate' => 'float',
        'rate_timestamp' => 'datetime',
        'normalised_values' => 'array',
        'sensitivity' => 'array',
        'buyer_position' => 'array',
        'source_attributions' => 'array',
        'as_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, DdValuation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdValuation>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<BusinessValuation, DdValuation>
     */
    public function businessValuation(): BelongsTo
    {
        return $this->belongsTo(BusinessValuation::class);
    }

    /**
     * @return BelongsTo<PvCalculation, DdValuation>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }

    /**
     * @return BelongsTo<ExchangeRate, DdValuation>
     */
    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }
}
