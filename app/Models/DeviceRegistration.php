<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeviceRegistration extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'mfa_confirmed_at' => 'datetime',
        'terms_confirmed_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function active(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return BelongsTo<User, DeviceRegistration>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, DeviceRegistration>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
