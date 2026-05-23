<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DdGuestLink extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'max_uploads' => 'integer',
        'upload_count' => 'integer',
    ];

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null || $this->expires_at === null || $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uploads === null || $this->upload_count < $this->max_uploads;
    }

    /**
     * @return BelongsTo<Client, DdGuestLink>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<DdEngagement, DdGuestLink>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(DdEngagement::class, 'dd_engagement_id');
    }

    /**
     * @return BelongsTo<User, DdGuestLink>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, DdGuestLink>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return HasMany<DdDataRoomItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DdDataRoomItem::class);
    }
}
