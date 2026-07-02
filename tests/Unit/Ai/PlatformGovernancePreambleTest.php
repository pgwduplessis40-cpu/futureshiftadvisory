<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Models\PlatformGovernanceVersion;
use App\Services\Ai\Prompts\IntegrityPreamble;
use App\Services\Ai\Prompts\PlatformGovernancePreamble;
use App\Support\RequestContext;
use Tests\TestCase;

final class PlatformGovernancePreambleTest extends TestCase
{
    public function test_renders_active_principles_and_roles_as_non_negotiable_ai_rules(): void
    {
        $version = new PlatformGovernanceVersion([
            'version' => 7,
            'principles' => [
                'Evidence must beat optimism.',
                'Contradictions must be surfaced to the advisor.',
            ],
            'roles' => [
                'FA&P (Financial Planning and Analysis)',
                'CFO (Chief Financial Officer)',
            ],
        ]);

        $payload = app(PlatformGovernancePreamble::class)->forVersion($version);

        $this->assertSame('platform-governance-v7', $payload['version']);
        $this->assertStringContainsString('non-negotiable system rules', $payload['text']);
        $this->assertStringContainsString('1. Evidence must beat optimism.', $payload['text']);
        $this->assertStringContainsString('2. Contradictions must be surfaced to the advisor.', $payload['text']);
        $this->assertStringContainsString('1. FA&P (Financial Planning and Analysis)', $payload['text']);
        $this->assertStringContainsString('2. CFO (Chief Financial Officer)', $payload['text']);
    }

    public function test_falls_back_to_static_integrity_preamble_when_no_version_exists(): void
    {
        $payload = new PlatformGovernancePreamble(app(RequestContext::class))->forVersion(null);

        $this->assertSame(IntegrityPreamble::VERSION, $payload['version']);
        $this->assertStringContainsString('AI Integrity Principle', $payload['text']);
    }
}
