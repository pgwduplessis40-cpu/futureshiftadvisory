<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperationalHealthCheckRun extends Model
{
    use HasUuids;

    public const STATUS_PASSED = 'passed';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'duration_ms' => 'integer',
        'total_checks' => 'integer',
        'passed_checks' => 'integer',
        'warning_checks' => 'integer',
        'failed_checks' => 'integer',
        'skipped_checks' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return HasMany<OperationalHealthCheckResult>
     */
    public function results(): HasMany
    {
        return $this->hasMany(OperationalHealthCheckResult::class, 'run_id');
    }
}
