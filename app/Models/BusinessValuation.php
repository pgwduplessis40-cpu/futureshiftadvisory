<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BusinessValuation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'sde_value' => 'array',
        'ebitda_value' => 'array',
        'dcf_value' => 'array',
        'reconciled_low' => 'float',
        'reconciled_mid' => 'float',
        'reconciled_high' => 'float',
        'adjustments' => 'array',
        'source_attributions' => 'array',
        'as_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, BusinessValuation>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<PvCalculation, BusinessValuation>
     */
    public function pvCalculation(): BelongsTo
    {
        return $this->belongsTo(PvCalculation::class);
    }
}
