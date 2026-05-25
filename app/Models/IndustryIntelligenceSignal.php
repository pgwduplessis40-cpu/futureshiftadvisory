<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class IndustryIntelligenceSignal extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'aggregate' => 'array',
        'cohort_size' => 'integer',
        'generated_at' => 'datetime',
        'suppressed' => 'boolean',
        'alerted_at' => 'datetime',
    ];
}
