<?php

declare(strict_types=1);

namespace App\Models;

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

    protected $guarded = [];

    protected $casts = [
        'value' => 'float',
        'period_date' => 'date',
        'degraded' => 'boolean',
        'fetched_at' => 'datetime',
        'payload' => 'array',
    ];
}
