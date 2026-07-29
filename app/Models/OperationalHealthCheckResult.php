<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperationalHealthCheckResult extends Model
{
    use HasUuids;

    public const STATUS_PASSED = 'passed';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    protected $casts = [
        'expected_statuses' => 'array',
        'actual_status' => 'integer',
        'response_time_ms' => 'integer',
        'actor_user_id' => 'integer',
        'consecutive_failures' => 'integer',
        'failures_last_7_days' => 'integer',
        'failures_last_30_days' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'context' => 'array',
    ];

    /**
     * @return BelongsTo<OperationalHealthCheckRun, OperationalHealthCheckResult>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OperationalHealthCheckRun::class, 'run_id');
    }

    public function needsAttention(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_WARNING,
            self::STATUS_SKIPPED,
        ], true);
    }
}
