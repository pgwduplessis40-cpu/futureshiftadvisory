<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class IntegrationHealthAlert extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'stuck_started_at' => 'datetime',
        'last_red_window_end' => 'datetime',
        'notified_at' => 'datetime',
    ];
}
