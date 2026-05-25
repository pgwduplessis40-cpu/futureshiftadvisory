<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SharedIntelligencePattern extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'pattern' => 'array',
        'cohort_size' => 'integer',
        'generated_at' => 'datetime',
    ];
}
