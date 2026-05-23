<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class NzResource extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'gap_tags' => 'array',
        'metadata' => 'array',
        'active' => 'boolean',
    ];
}
