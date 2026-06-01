<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IntegrationCredentials
{
    public function __construct(
        private readonly KeyEnvelope $envelope,
        private readonly IntegrationRegistry $registry,
        private readonly AuditWriter $audit,
    ) {}

    public function get(string $integrationKey, string $field): ?string
    {
        $credential = IntegrationCredential::query()
            ->where('integration_key', $integrationKey)
            ->where('field', $field)
            ->first();

        if ($credential instanceof IntegrationCredential) {
            if (! $credential->active()) {
                return null;
            }

            return $this->envelope->decrypt((string) $credential->value_envelope);
        }

        return $this->fallback($integrationKey, $field);
    }

    public function present(string $integrationKey, string $field): bool
    {
        $value = $this->get($integrationKey, $field);

        return is_string($value) && trim($value) !== '';
    }

    public function set(string $integrationKey, string $field, string $value, User $actor): IntegrationCredential
    {
        $value = trim($value);
        $existing = IntegrationCredential::query()
            ->where('integration_key', $integrationKey)
            ->where('field', $field)
            ->first();
        $action = $existing?->status === IntegrationCredential::STATUS_ACTIVE
            ? 'credential.rotated'
            : 'credential.set';
        $ciphertext = $this->envelope->encrypt($value);
        $meta = $this->envelope->inspect($ciphertext);

        /** @var IntegrationCredential $credential */
        $credential = DB::transaction(function () use ($existing, $integrationKey, $field, $value, $ciphertext, $meta, $actor, $action): IntegrationCredential {
            $credential = $existing ?? new IntegrationCredential([
                'integration_key' => $integrationKey,
                'field' => $field,
            ]);

            $credential->forceFill([
                'value_envelope' => $ciphertext,
                'value_envelope_meta' => $meta,
                'last_four' => Str::substr($value, -4),
                'status' => IntegrationCredential::STATUS_ACTIVE,
                'set_by_user_id' => $actor->getKey(),
                'rotated_at' => $action === 'credential.rotated' ? now() : $credential->rotated_at,
                'revoked_at' => null,
            ])->save();

            $this->audit->record($action, subject: $credential, actor: $actor, after: [
                'integration_key' => $integrationKey,
                'field' => $field,
                'status' => IntegrationCredential::STATUS_ACTIVE,
                'last_four' => $credential->last_four,
            ]);

            return $credential;
        });

        return $credential;
    }

    public function revoke(string $integrationKey, string $field, User $actor): ?IntegrationCredential
    {
        $credential = IntegrationCredential::query()
            ->where('integration_key', $integrationKey)
            ->where('field', $field)
            ->first();

        if (! $credential instanceof IntegrationCredential) {
            return null;
        }

        $credential->forceFill([
            'status' => IntegrationCredential::STATUS_REVOKED,
            'value_envelope' => null,
            'value_envelope_meta' => null,
            'revoked_at' => now(),
            'set_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('credential.revoked', subject: $credential, actor: $actor, after: [
            'integration_key' => $integrationKey,
            'field' => $field,
            'status' => IntegrationCredential::STATUS_REVOKED,
            'last_four' => $credential->last_four,
        ]);

        return $credential;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function registryRows(): Collection
    {
        $stored = IntegrationCredential::query()
            ->with('setBy')
            ->get()
            ->keyBy(fn (IntegrationCredential $credential): string => $credential->integration_key.'|'.$credential->field);

        return $this->registry->all()
            ->map(function (array $integration) use ($stored): array {
                $credentials = $integration['credentials'] ?? [];
                $integrationKey = (string) $integration['integration_key'];

                return [
                    'integration_key' => $integrationKey,
                    'display_name' => $integration['display_name'] ?? $integrationKey,
                    'category' => $integration['category'] ?? 'other',
                    'fallback_mode' => $integration['fallback_mode'] ?? 'optional',
                    'managed_via' => $integration['managed_via'] ?? 'vault',
                    'wiring_status' => $integration['wiring_status'] ?? 'wired',
                    'credentials' => is_array($credentials)
                        ? collect($credentials)
                            ->map(function (array $credentialDefinition, string $field) use ($integrationKey, $stored): array {
                                $storedCredential = $stored->get($integrationKey.'|'.$field);

                                return [
                                    'field' => $field,
                                    'config_path' => $credentialDefinition['config_path'] ?? null,
                                    'env_fallback_path' => $credentialDefinition['env_fallback_path'] ?? null,
                                    'status' => $storedCredential?->status,
                                    'last_four' => $storedCredential?->last_four,
                                    'rotated_at' => $storedCredential?->rotated_at?->toIso8601String(),
                                    'revoked_at' => $storedCredential?->revoked_at?->toIso8601String(),
                                    'set_by' => $storedCredential?->setBy?->name,
                                    'has_env_fallback' => $storedCredential === null && $this->fallback($integrationKey, $field) !== null,
                                ];
                            })
                            ->values()
                            ->all()
                        : [],
                ];
            })
            ->values();
    }

    private function fallback(string $integrationKey, string $field): ?string
    {
        $definition = $this->registry->credential($integrationKey, $field);
        $configPath = $definition['config_path'] ?? null;

        if (! is_string($configPath) || $configPath === '') {
            return null;
        }

        $value = Config::get($configPath);

        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
