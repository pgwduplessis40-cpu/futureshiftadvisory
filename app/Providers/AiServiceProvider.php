<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\Claude\AnthropicClaudeClient;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Ai\FallbackAiClient;
use App\Services\Ai\Integrity\BiasDetector;
use App\Services\Ai\Integrity\SourceAttribution;
use App\Services\Ai\IntegrityCheckedAiClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeAiClient::class);
        $this->app->singleton(AnthropicClaudeClient::class);
        $this->app->singleton(AdvisorAiNotice::class);
        $this->app->singleton(SourceAttribution::class);
        $this->app->singleton(BiasDetector::class);

        $this->app->singleton(AiClient::class, function (): AiClient {
            $forceFake = $this->app->environment('testing')
                || blank((string) Config::get('services.anthropic.key', ''));

            $delegate = new FallbackAiClient(
                live: $forceFake ? null : $this->app->make(AnthropicClaudeClient::class),
                fake: $this->app->make(FakeAiClient::class),
                notice: $this->app->make(AdvisorAiNotice::class),
                forceFake: $forceFake,
            );

            return new IntegrityCheckedAiClient(
                delegate: $delegate,
                sourceAttribution: $this->app->make(SourceAttribution::class),
                biasDetector: $this->app->make(BiasDetector::class),
            );
        });
    }
}
