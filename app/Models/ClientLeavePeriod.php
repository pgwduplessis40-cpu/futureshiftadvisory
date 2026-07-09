<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClientLeavePeriod extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * @return BelongsTo<Client, ClientLeavePeriod>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, ClientLeavePeriod>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<ClientLeavePeriod>  $query
     * @return Builder<ClientLeavePeriod>
     */
    public function scopeOverlapping(Builder $query, mixed $start, mixed $end): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start);
    }
}
