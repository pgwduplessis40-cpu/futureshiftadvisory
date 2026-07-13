<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\AdvisorAiNotice;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Exceptions\AiIntegrityViolation;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Ai\FallbackAiClient;
use Tests\TestCase;

final class FallbackAiClientTest extends TestCase
{
    public function test_integrity_violations_return_a_governed_degraded_response(): void
    {
        $prompt = new PromptEnvelope(
            id: 'fallback.integrity.test',
            version: 'v1',
            task: 'analyse',
            body: 'Assess the supplied information.',
            input: ['idea' => 'Example concept'],
        );
        $client = new FallbackAiClient(
            live: new IntegrityFailingAiClient,
            fake: new FakeAiClient,
            notice: app(AdvisorAiNotice::class),
            forceFake: false,
        );

        $response = $client->analyse($prompt);

        $this->assertSame(FakeAiClient::DEGRADED_TEXT, $response->text);
        $this->assertSame('fake-ai-client', $response->model);
        $this->assertTrue((bool) ($response->metadata['degraded'] ?? false));
        $this->assertSame(
            'Anthropic response was freeform prose instead of the required JSON schema.',
            $response->metadata['unavailable_reason'] ?? null,
        );
    }
}

final class IntegrityFailingAiClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $this->fail();
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        $this->fail();
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        $this->fail();
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        $this->fail();
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        $this->fail();
    }

    private function fail(): never
    {
        throw new AiIntegrityViolation('Anthropic response was freeform prose instead of the required JSON schema.');
    }
}
