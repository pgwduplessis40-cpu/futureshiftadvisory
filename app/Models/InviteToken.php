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

    public function serviceIntentLabel(): ?string
    {
        return match ($this->intended_service_type) {
            ServiceActivation::SERVICE_ENTREPRENEUR => 'Business Idea',
            ServiceActivation::SERVICE_DUE_DILIGENCE => 'Buying a Business',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serviceIntentPayload(): ?array
    {
        $label = $this->serviceIntentLabel();

        if ($label === null) {
            return null;
        }

        return [
            'service_type' => $this->intended_service_type,
            'package_scope' => $this->intended_package_scope,
            'label' => $label,
            'package_scope_label' => $this->intended_package_scope !== null
                ? ServiceRatePackage::packageScopeLabel($this->intended_package_scope)
                : $this->serviceIntentFallbackScopeLabel(),
            'access' => ServiceRatePackage::accessFor(
                (string) $this->intended_service_type,
                is_string($this->intended_package_scope) ? $this->intended_package_scope : null,
            ),
        ];
    }

    private function serviceIntentFallbackScopeLabel(): string
    {
        return match ($this->intended_service_type) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => 'Due diligence workspace',
            ServiceActivation::SERVICE_ENTREPRENEUR => 'Entrepreneur workspace',
            default => 'Standard workspace',
        };
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
