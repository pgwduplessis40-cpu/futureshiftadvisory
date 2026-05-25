<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class IndustryWaccData extends Model
{
    use HasUuids;

    protected $table = 'industry_wacc_data';

    protected $guarded = [];

    protected $casts = [
        'wacc_rate' => 'float',
        'cost_of_equity' => 'float',
        'cost_of_debt' => 'float',
        'equity_weight' => 'float',
        'debt_weight' => 'float',
        'degraded' => 'boolean',
        'fetched_at' => 'datetime',
        'superseded_at' => 'datetime',
        'payload' => 'array',
    ];
}
