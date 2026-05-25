<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Security\MfaChallenger;
use App\Services\Terms\TermsAcceptanceGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class MobileTokenIssuer
{
    public function __construct(
        private readonly MfaChallenger $mfa,
        private readonly TermsAcceptanceGate $terms,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $device
     * @return array{device:DeviceRegistration, token:string}
     */
    public function issue(User $user, array $device, ?User $actor = null): array
    {
        $this->assertUserCanUseMobile($user);

        $plainToken = 'fsa_mobile_'.Str::random(72);
        $tokenHash = hash('sha256', $plainToken);
        $deviceId = $this->required($device, 'device_id');
        $platform = $this->required($device, 'platform');

        return DB::transaction(function () use ($user, $actor, $device, $deviceId, $platform, $plainToken, $tokenHash): array {
            DeviceRegistration::query()
                ->where('user_id', $user->getKey())
                ->where('device_id', $deviceId)
                ->where('status', DeviceRegistration::STATUS_ACTIVE)
                ->update([
                    'status' => DeviceRegistration::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $actor?->getKey() ?? $user->getKey(),
                ]);

            /** @var DeviceRegistration $registration */
            $registration = DeviceRegistration::query()->create([
                'user_id' => $user->getKey(),
                'device_id' => $deviceId,
                'platform' => $platform,
                'device_name' => $device['device_name'] ?? null,
                'app_version' => $device['app_version'] ?? null,
                'token_hash' => $tokenHash,
                'status' => DeviceRegistration::STATUS_ACTIVE,
                'capabilities' => is_array($device['capabilities'] ?? null) ? $device['capabilities'] : [],
                'mfa_confirmed_at' => $user->mfa_enabled_at,
                'terms_confirmed_at' => now(),
                'expires_at' => now()->addDays(90),
            ]);

            $this->audit->record('mobile_device.registered', subject: $registration, actor: $actor ?? $user, after: [
                'platform' => $platform,
                'device_id' => $deviceId,
                'app_version' => $registration->app_version,
            ]);

            return [
                'device' => $registration->refresh(),
                'token' => $plainToken,
            ];
        });
    }

    public function revoke(DeviceRegistration $registration, User $actor): DeviceRegistration
    {
        $registration->forceFill([
            'status' => DeviceRegistration::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('mobile_device.revoked', subject: $registration, actor: $actor, after: [
            'device_id' => $registration->device_id,
            'platform' => $registration->platform,
        ]);

        return $registration->refresh();
    }

    private function assertUserCanUseMobile(User $user): void
    {
        if ($user->suspended_at !== null) {
            throw new InvalidArgumentException('Suspended users cannot register mobile devices.');
        }

        if (! $this->mfa->hasCompletedEnrolment($user)) {
            throw new InvalidArgumentException('Mobile device registration requires MFA enrolment.');
        }

        if ($this->terms->hasDeclinedTermsSuspension($user) || $this->terms->requiresAcceptance($user)) {
            throw new InvalidArgumentException('Mobile device registration requires accepted current terms.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function required(array $input, string $key): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }
}
