<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Prompts\IntegrityPreamble;
use App\Services\Ai\Prompts\PromptRegistry;
use Tests\TestCase;

final class PromptRegistryTest extends TestCase
{
    public function test_builds_prompt_envelope_with_integrity_preamble(): void
    {
        $registry = new PromptRegistry;
        $registry->register('demo.prompt', 'v1', 'Respond with the schema.', 'analyse');

        $envelope = $registry->envelope(
            id: 'demo.prompt',
            input: ['claim' => 'A claim'],
            dataQualitySummary: ['quality' => 'medium'],
            sourceReferences: ['source:one'],
        );

        $this->assertSame('demo.prompt', $envelope->id);
        $this->assertSame('v1', $envelope->version);
        $this->assertSame(IntegrityPreamble::VERSION, $envelope->integrityPreambleVersion);
        $this->assertStringContainsString('AI Integrity Principle', $envelope->integrityPreamble);
        $matchingEnvelope = $registry->envelope(
            id: 'demo.prompt',
            input: ['claim' => 'A claim'],
            dataQualitySummary: ['quality' => 'medium'],
            sourceReferences: ['source:one'],
        );

        $this->assertSame($envelope->hash(), $matchingEnvelope->hash());
    }
}
