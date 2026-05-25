<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LearningLayerState extends Model
{
    protected $table = 'learning_layer_state';

    protected $primaryKey = 'layer_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'layer_id' => 'integer',
        'active' => 'boolean',
        'min_sample' => 'integer',
        'last_run_at' => 'datetime',
        'next_due_at' => 'datetime',
        'config' => 'array',
    ];
}
