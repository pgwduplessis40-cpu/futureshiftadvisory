<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntegrationCredential extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $hidden = [
        'value_envelope',
    ];

    protected $casts = [
        'value_envelope_meta' => 'array',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function active(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->value_envelope !== null;
    }

    /**
     * @return BelongsTo<User, IntegrationCredential>
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
