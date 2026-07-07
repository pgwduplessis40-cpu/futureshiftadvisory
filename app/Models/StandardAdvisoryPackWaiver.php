<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StandardAdvisoryPackWaiver extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'modules' => 'array',
        'waived_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Client, StandardAdvisoryPackWaiver>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, StandardAdvisoryPackWaiver>
     */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by_user_id');
    }

    /**
     * @param  Builder<StandardAdvisoryPackWaiver>  $query
     * @return Builder<StandardAdvisoryPackWaiver>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function coversModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }
}
