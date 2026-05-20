<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class IntegrationHealthSample extends Model
{
    use HasUuids;

    public const HEALTH_GREEN = 'green';

    public const HEALTH_AMBER = 'amber';

    public const HEALTH_RED = 'red';

    protected $guarded = [];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'success_rate' => 'float',
        'p95_latency_ms' => 'integer',
    ];
}
