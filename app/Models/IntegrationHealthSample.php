<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-import-type HealthIssue from \App\Services\Integration\IntegrationHealthIssue
 *
 * @phpstan-type HealthDiagnostics array{availability:string,response_time:string,green_success_rate:float,amber_success_rate:float,green_latency_ms:int,amber_latency_ms:int,latency_red_min_samples:int,event_count:int,success_count:int,retry_count:int,failure_count:int,fallback_count:int,cached_count:int,latest_issue:HealthIssue|null}
 *
 * @property HealthDiagnostics|null $diagnostics
 */
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
        'diagnostics' => 'array',
    ];
}
