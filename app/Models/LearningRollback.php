<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearningRollback extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'rolled_back_at' => 'datetime',
        'restored_state' => 'array',
    ];

    /**
     * @return BelongsTo<LearningUpdate, $this>
     */
    public function learningUpdate(): BelongsTo
    {
        return $this->belongsTo(LearningUpdate::class);
    }

    /**
     * @return BelongsTo<LearningUpdateImplementation, $this>
     */
    public function implementation(): BelongsTo
    {
        return $this->belongsTo(LearningUpdateImplementation::class, 'learning_update_implementation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by_user_id');
    }
}
