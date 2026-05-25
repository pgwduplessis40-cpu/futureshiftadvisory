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

    protected $guarded = [];

    protected $casts = [
        'from_meta' => 'array',
        'to_meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
