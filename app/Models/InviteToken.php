<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class InviteToken extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return $this->expires_at->lte($now ?? now());
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isUsable(?Carbon $now = null): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired($now);
    }

    public function markAccepted(User $user): void
    {
        $this->forceFill([
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->getKey(),
        ])->save();
    }

    /**
     * @return BelongsTo<User, InviteToken>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * @return BelongsTo<User, InviteToken>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }
}
