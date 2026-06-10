<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AiUsageEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_creation_input_tokens' => 'integer',
        'cache_read_input_tokens' => 'integer',
        'estimated_cost_usd' => 'float',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
