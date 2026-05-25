<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NzToolConnection extends Model
{
    use HasUuids;

    public const PROVIDER_EMPLOYMENT_HERO = 'employment_hero';

    public const PROVIDER_CIN7 = 'cin7';

    public const PROVIDER_TRADIFY = 'tradify';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $hidden = [
        'token_envelope',
    ];

    protected $casts = [
        'token_envelope_meta' => 'array',
        'scopes' => 'array',
        'last_sync_payload' => 'array',
        'connected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            self::PROVIDER_EMPLOYMENT_HERO => 'Employment Hero',
            self::PROVIDER_CIN7 => 'Cin7',
            self::PROVIDER_TRADIFY => 'Tradify',
        ];
    }

    public static function validProvider(string $provider): bool
    {
        return array_key_exists($provider, self::providerLabels());
    }

    public function providerLabel(): string
    {
        return self::providerLabels()[$this->provider] ?? $this->provider;
    }

    public function connected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->revoked_at === null;
    }

    /**
     * @return BelongsTo<Client, NzToolConnection>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, NzToolConnection>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }
}
