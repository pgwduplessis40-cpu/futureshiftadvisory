<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class AccountingConnection extends Model
{
    use HasUuids;

    public const PROVIDER_XERO = 'xero';

    public const PROVIDER_MYOB = 'myob';

    public const PROVIDER_QUICKBOOKS = 'quickbooks';

    public const PROVIDER_SAGE = 'sage';

    public const PROVIDER_FIGURED = 'figured';

    public const PROVIDER_WORKFLOWMAX = 'workflowmax';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $hidden = [
        'token_envelope',
    ];

    protected $casts = [
        'token_envelope_meta' => 'array',
        'scopes' => 'array',
        'connected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_snapshot_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            self::PROVIDER_XERO => 'Xero',
            self::PROVIDER_MYOB => 'MYOB',
            self::PROVIDER_QUICKBOOKS => 'QuickBooks',
            self::PROVIDER_SAGE => 'Sage',
            self::PROVIDER_FIGURED => 'Figured',
            self::PROVIDER_WORKFLOWMAX => 'WorkflowMax',
        ];
    }

    /**
     * @param  array<int, string>  $connectedProviders
     * @param  callable(string): bool  $isLive
     * @return array<string, string>
     */
    public static function applicableProviderLabels(array $connectedProviders, callable $isLive): array
    {
        return collect(self::providerLabels())
            ->filter(
                fn (string $label, string $provider): bool => in_array($provider, $connectedProviders, true)
                    || $isLive($provider),
            )
            ->all();
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
     * @return BelongsTo<Client, AccountingConnection>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, AccountingConnection>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    /**
     * @return HasMany<FinancialSnapshot>
     */
    public function financialSnapshots(): HasMany
    {
        return $this->hasMany(FinancialSnapshot::class);
    }

    /**
     * @return HasOne<FinancialSnapshot>
     */
    public function latestFinancialSnapshot(): HasOne
    {
        return $this->hasOne(FinancialSnapshot::class)->latest('pulled_at');
    }
}
