<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class IntegrationCall extends Model
{
    use HasUuids;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_RETRY = 'retry';

    public const STATUS_FAILURE = 'failure';

    public const STATUS_CACHED = 'cached';

    public const STATUS_FALLBACK = 'fallback';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'attempt' => 'integer',
        'latency_ms' => 'integer',
        'error_payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
