<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiClient;
use App\Services\Integration\IntegrationActivationResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class AiProviderManager
{
    public function __construct(
        private readonly Container $container,
        private readonly IntegrationActivationResolver $activations,
    ) {}

    public function activeProviderKey(): string
    {
        $key = Config::get('ai.active_provider', Config::get('ai.default_provider', 'anthropic'));

        return is_string($key) && trim($key) !== '' ? trim($key) : 'anthropic';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeProvider(): ?array
    {
        return $this->provider($this->activeProviderKey());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(string $key): ?array
    {
        $definition = Config::get("ai.providers.{$key}");

        if (! is_array($definition)) {
            return null;
        }

        return [
            'key' => $key,
            'display_name' => (string) ($definition['display_name'] ?? $key),
            'integration_key' => (string) ($definition['integration_key'] ?? $key),
            'client' => $definition['client'] ?? null,
            'status' => (string) ($definition['status'] ?? 'available'),
        ];
    }

    public function liveClient(): ?AiClient
    {
        $provider = $this->activeProvider();

        if ($provider === null || $provider['status'] !== 'available') {
            return null;
        }

        if (! $this->activations->isLive((string) $provider['integration_key'])) {
            return null;
        }

        $clientClass = $provider['client'];
        if (! is_string($clientClass) || ! is_a($clientClass, AiClient::class, true)) {
            throw new InvalidArgumentException('Configured AI provider client must implement '.AiClient::class.'.');
        }

        return $this->container->make($clientClass);
    }

    public function unavailableReason(): string
    {
        $key = $this->activeProviderKey();
        $provider = $this->activeProvider();

        if ($provider === null) {
            return "AI provider [{$key}] is not registered.";
        }

        if ($provider['status'] !== 'available') {
            return "AI provider [{$key}] is not marked available.";
        }

        return "AI provider [{$key}] is not active or its credentials are missing.";
    }
}
