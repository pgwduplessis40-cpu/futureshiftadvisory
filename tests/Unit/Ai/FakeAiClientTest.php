<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Fake\FakeAiClient;
use Tests\TestCase;

final class FakeAiClientTest extends TestCase
{
    public function test_returns_deterministic_degraded_response(): void
    {
        $prompt = new PromptEnvelope(
            id: 'demo',
            version: 'v1',
            task: 'summarise',
            body: 'Summarise this.',
            input: ['one' => 'two'],
        );

        $client = new FakeAiClient;
        $first = $client->summarise($prompt);
        $second = $client->summarise($prompt);

        $this->assertSame(FakeAiClient::DEGRADED_TEXT, $first->text);
        $this->assertSame(Uncertainty::High, $first->uncertainty);
        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame('system:degraded-mode', $first->attributions[0]['source_reference']);
    }
}
