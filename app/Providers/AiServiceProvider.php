<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Ai\FallbackAiClient;
use App\Services\Ai\Integrity\BiasDetector;
use App\Services\Ai\Integrity\SourceAttribution;
use App\Services\Ai\IntegrityCheckedAiClient;
use App\Services\Ai\Prompts\GovernancePreambleProvider;
use App\Services\Ai\Prompts\PlatformGovernancePreamble;
use App\Services\Ai\Prompts\PromptRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeAiClient::class);
        $this->app->singleton(AnthropicClaudeClient::class);
        $this->app->singleton(AiProviderManager::class);
        $this->app->singleton(AdvisorAiNotice::class);
        $this->app->singleton(SourceAttribution::class);
        $this->app->singleton(BiasDetector::class);
        $this->app->singleton(GovernancePreambleProvider::class, PlatformGovernancePreamble::class);
        $this->app->singleton(
            PromptRegistry::class,
            fn (): PromptRegistry => new PromptRegistry($this->app->make(GovernancePreambleProvider::class)),
        );

        $this->app->singleton(AiClient::class, function (): AiClient {
            $provider = $this->app->make(AiProviderManager::class);
            $forceFake = $this->app->environment('testing')
                || (bool) Config::get('ai.force_fake', false);

            $delegate = new FallbackAiClient(
                live: $forceFake ? null : $provider->liveClient(),
                fake: $this->app->make(FakeAiClient::class),
                notice: $this->app->make(AdvisorAiNotice::class),
                forceFake: $forceFake,
                unavailableReason: $forceFake
                    ? 'AI provider is forced into governed degraded mode.'
                    : $provider->unavailableReason(),
            );

            return new IntegrityCheckedAiClient(
                delegate: $delegate,
                sourceAttribution: $this->app->make(SourceAttribution::class),
                biasDetector: $this->app->make(BiasDetector::class),
            );
        });
    }
}
