<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ValuationMultiple extends Model
{
    use HasUuids;

    public const METRIC_EBITDA = 'ebitda';

    public const METRIC_SDE = 'sde';

    public const SOURCE_MBIE = 'mbie';

    public const SOURCE_NZ_BUSINESS_BROKERS = 'nz_business_brokers';

    protected $guarded = [];

    protected $casts = [
        'multiple_low' => 'float',
        'multiple_mid' => 'float',
        'multiple_high' => 'float',
        'degraded' => 'boolean',
        'fetched_at' => 'datetime',
        'superseded_at' => 'datetime',
        'payload' => 'array',
    ];
}
