<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CalendarConnection extends Model
{
    use HasUuids;

    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDER_MICROSOFT = 'microsoft';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    protected $guarded = [];

    protected $hidden = [
        'access_token_envelope',
        'refresh_token_envelope',
    ];

    protected $casts = [
        'access_token_envelope_meta' => 'array',
        'refresh_token_envelope_meta' => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public static function providers(): array
    {
        return [
            self::PROVIDER_GOOGLE,
            self::PROVIDER_MICROSOFT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            self::PROVIDER_GOOGLE => 'Google Calendar',
            self::PROVIDER_MICROSOFT => 'Microsoft Outlook',
        ];
    }

    public static function validProvider(string $provider): bool
    {
        return array_key_exists($provider, self::providerLabels());
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_CONNECTED,
            self::STATUS_REVOKED,
            self::STATUS_ERROR,
        ];
    }

    public function providerLabel(): string
    {
        return self::providerLabels()[$this->provider] ?? $this->provider;
    }

    public function connected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * @param  Builder<CalendarConnection>  $query
     * @return Builder<CalendarConnection>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /**
     * @param  Builder<CalendarConnection>  $query
     * @return Builder<CalendarConnection>
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONNECTED);
    }

    /**
     * @return BelongsTo<User, CalendarConnection>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CalendarEventMapping>
     */
    public function eventMappings(): HasMany
    {
        return $this->hasMany(CalendarEventMapping::class);
    }
}
