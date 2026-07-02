<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class AiProviderSwitchingTest extends TestCase
{
    public function test_provider_manager_can_resolve_a_non_anthropic_ai_client(): void
    {
        Config::set('ai.active_provider', 'replacement');
        Config::set('ai.providers.replacement', [
            'display_name' => 'Replacement AI',
            'integration_key' => 'replacement_ai',
            'client' => ReplacementAiClient::class,
            'status' => 'available',
        ]);
        Config::set('integration_registry.integrations.replacement_ai', [
            'display_name' => 'Replacement AI',
            'category' => 'ai',
            'fallback_mode' => 'api_required',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'credentials' => [],
        ]);

        $client = app(AiProviderManager::class)->liveClient();

        $this->assertInstanceOf(ReplacementAiClient::class, $client);
        $this->assertSame(
            'replacement-ai-model',
            $client->summarise($this->prompt())->model,
        );
    }

    public function test_prompt_hash_and_governance_context_survive_provider_change(): void
    {
        $prompt = $this->prompt();
        $anthropicStyleResponse = (new PortableAiClient('anthropic-claude'))->summarise($prompt);
        $replacementResponse = (new PortableAiClient('replacement-ai-model'))->summarise($prompt);

        $this->assertSame($anthropicStyleResponse->promptHash, $replacementResponse->promptHash);
        $this->assertSame($prompt->hash(), $replacementResponse->promptHash);
        $this->assertNotSame($anthropicStyleResponse->model, $replacementResponse->model);
        $this->assertStringContainsString('platform-governance', $prompt->integrityPreambleVersion);
    }

    private function prompt(): PromptEnvelope
    {
        return new PromptEnvelope(
            id: 'provider.switch.test',
            version: 'v1',
            task: 'summarise',
            body: 'Summarise without adding facts.',
            input: ['client_context' => 'Known facts remain in the FSA system.'],
            integrityPreamble: 'Active governance rules stay in the FSA system.',
            integrityPreambleVersion: 'platform-governance-v1',
        );
    }
}

class PortableAiClient implements AiClient
{
    public function __construct(private readonly string $model) {}

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->response($prompt);
    }

    private function response(PromptEnvelope $prompt): AiResponse
    {
        return new AiResponse(
            text: 'Provider-neutral response.',
            attributions: [
                [
                    'claim' => 'Provider-neutral response.',
                    'source_reference' => 'system:provider-switch-test',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: $this->model,
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 1,
            tokensOut: 1,
        );
    }
}

final class ReplacementAiClient extends PortableAiClient
{
    public function __construct()
    {
        parent::__construct('replacement-ai-model');
    }
}
