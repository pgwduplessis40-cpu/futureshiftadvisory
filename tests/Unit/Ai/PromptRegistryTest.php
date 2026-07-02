<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Prompts\GovernancePreambleProvider;
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

    public function test_builds_prompt_envelope_with_active_platform_governance_rules(): void
    {
        $registry = new PromptRegistry(new FakeGovernancePreambleProvider);
        $registry->register('demo.prompt', 'v1', 'Respond with the schema.', 'analyse');

        $envelope = $registry->envelope(id: 'demo.prompt');

        $this->assertSame('platform-governance-v9', $envelope->integrityPreambleVersion);
        $this->assertStringContainsString('non-negotiable system rules', $envelope->integrityPreamble);
        $this->assertStringContainsString('Never suppress cash-flow risk.', $envelope->integrityPreamble);
        $this->assertStringContainsString('CFO (Chief Financial Officer)', $envelope->integrityPreamble);
    }
}

final class FakeGovernancePreambleProvider implements GovernancePreambleProvider
{
    public function active(): array
    {
        return [
            'text' => <<<'TEXT'
AI Integrity Principle:
The active Future Shift Advisory Principles & Roles are non-negotiable system rules.
1. Never suppress cash-flow risk.
Roles:
1. CFO (Chief Financial Officer)
TEXT,
            'version' => 'platform-governance-v9',
        ];
    }
}
