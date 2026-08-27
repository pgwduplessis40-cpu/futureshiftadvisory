<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CryptoRotation extends Model
{
    use HasUuids;

    public const STATUS_REWRAPPED = 'rewrapped';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROTATED = 'rotated';

    protected $fillable = [
        'run_id',
        'rotation_type',
        'source_table',
        'source_column',
        'source_id',
        'from_version',
        'from_alg',
        'from_kid',
        'to_version',
        'to_alg',
        'to_kid',
        'from_meta',
        'to_meta',
        'status',
        'idempotency_key',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'from_meta' => 'array',
        'to_meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
