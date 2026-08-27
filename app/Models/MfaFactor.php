<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MfaFactor extends Model
{
    use HasUuids;

    public const TYPE_TOTP = 'totp';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'label',
        'secret_envelope',
        'recovery_codes_envelope',
        'confirmed_at',
        'last_used_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, MfaFactor>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
