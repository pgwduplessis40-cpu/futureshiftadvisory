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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class IntegrationCredentials
{
    /**
     * @var array<string, array{purpose: string, api_outcome: string}>
     */
    private const DESCRIPTIONS = [
        'employment_hero' => [
            'purpose' => 'Employment Hero is the HR and payroll platform used to understand workforce structure, employee cost drivers, leave patterns, and people-related operating risk.',
            'api_outcome' => 'Activating the API allows Future Shift Advisory to pull workforce and payroll context into staffing, capacity, compliance, and profitability analysis without manually re-keying HR data.',
        ],
        'cin7' => [
            'purpose' => 'Cin7 is the inventory, order, and product-management platform used to understand stock movement, fulfilment, sales mix, and working-capital pressure.',
            'api_outcome' => 'Activating the API allows Future Shift Advisory to enrich advisory work with inventory, order, product, and customer signals for margin, cash-flow, operational-risk, and valuation analysis.',
        ],
        'tradify' => [
            'purpose' => 'Tradify is the job-management platform for trade and service businesses, covering quotes, jobs, time, materials, invoices, and workflow progress.',
            'api_outcome' => 'Activating the API allows Future Shift Advisory to connect job pipeline, labour utilisation, work-in-progress, and job profitability signals to operational and cash-flow advice.',
        ],
    ];

    private ?bool $credentialStoreAvailable = null;

    public function __construct(
        private readonly KeyEnvelope $envelope,
        private readonly IntegrationRegistry $registry,
        private readonly AuditWriter $audit,
    ) {}

    public function get(string $integrationKey, string $field): ?string
    {
        if ($this->credentialStoreAvailable()) {
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
        abort_unless($this->credentialStoreAvailable(), 503, 'Integration credential store is not migrated.');

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
        abort_unless($this->credentialStoreAvailable(), 503, 'Integration credential store is not migrated.');

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
        $stored = $this->credentialStoreAvailable()
            ? IntegrationCredential::query()
                ->with('setBy')
                ->get()
                ->keyBy(fn (IntegrationCredential $credential): string => $credential->integration_key.'|'.$credential->field)
            : collect();

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
                    'purpose' => $this->description($integrationKey, $integration, 'purpose'),
                    'api_outcome' => $this->description($integrationKey, $integration, 'api_outcome'),
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

    /**
     * @param  array<string, mixed>  $integration
     */
    private function description(string $integrationKey, array $integration, string $field): string
    {
        $configured = $integration[$field] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $known = self::DESCRIPTIONS[$integrationKey][$field] ?? null;
        if (is_string($known) && $known !== '') {
            return $known;
        }

        $displayName = (string) ($integration['display_name'] ?? Str::headline($integrationKey));
        $category = str_replace('_', ' ', (string) ($integration['category'] ?? 'integration'));

        return $field === 'purpose'
            ? "{$displayName} is a {$category} integration used to connect trusted external data to the advisory workflow."
            : "Activating the API allows Future Shift Advisory to use {$displayName} data in live workflows while keeping credentials governed through the credential vault.";
    }

    private function credentialStoreAvailable(): bool
    {
        return $this->credentialStoreAvailable ??= Schema::hasTable('integration_credentials');
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
