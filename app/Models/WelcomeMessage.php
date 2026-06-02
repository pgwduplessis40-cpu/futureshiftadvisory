<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WelcomeMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WelcomeMessage>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<WelcomeMessage>  $query
     * @return Builder<WelcomeMessage>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
