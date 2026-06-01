<?php

declare(strict_types=1);

namespace App\Services\Integration;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

final class IntegrationRegistry
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        /** @var array<string, array<string, mixed>> $integrations */
        $integrations = Config::get('integration_registry.integrations', []);

        return collect($integrations)
            ->map(fn (array $definition, string $key): array => [
                'integration_key' => $key,
                ...$definition,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function integration(string $integrationKey): ?array
    {
        $definition = Config::get("integration_registry.integrations.{$integrationKey}");

        return is_array($definition) ? $definition : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credential(string $integrationKey, string $field): ?array
    {
        $definition = Config::get("integration_registry.integrations.{$integrationKey}.credentials.{$field}");

        return is_array($definition) ? $definition : null;
    }

    /**
     * @return array<int, string>
     */
    public function credentialFields(string $integrationKey): array
    {
        /** @var array<string, mixed> $credentials */
        $credentials = Config::get("integration_registry.integrations.{$integrationKey}.credentials", []);

        return array_keys($credentials);
    }
}
