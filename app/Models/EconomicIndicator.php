<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class EconomicIndicator extends Model
{
    use HasUuids;

    public const OCR = 'ocr';

    public const CPI_ANNUAL = 'cpi_annual';

    public const GDP_QUARTERLY = 'gdp_quarterly';

    public const UNEMPLOYMENT_RATE = 'unemployment_rate';

    public const MINIMUM_WAGE = 'minimum_wage';

    public const LIVING_WAGE = 'living_wage';

    public const COMPANY_TAX_RATE = 'company_tax_rate';

    protected $guarded = [];

    protected $casts = [
        'value' => 'float',
        'period_date' => 'date',
        'degraded' => 'boolean',
        'fetched_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Restrict a query to values that are safe to present as current reference data.
     *
     * Fixture and degraded fallback rows remain stored for diagnostics, but must not
     * influence client-facing financial guidance or an OCR-linked calculation.
     *
     * @param  Builder<EconomicIndicator>  $query
     * @return Builder<EconomicIndicator>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->where('degraded', false)
            ->whereIn('source_badge', ['live', 'cached', 'manual_admin']);
    }
}
