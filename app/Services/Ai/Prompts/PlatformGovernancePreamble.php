<?php

declare(strict_types=1);

namespace App\Services\Ai\Prompts;

use App\Models\PlatformGovernanceVersion;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PlatformGovernancePreamble implements GovernancePreambleProvider
{
    public function __construct(private readonly RequestContext $context) {}

    /**
     * @return array{text:string,version:string}
     */
    public function active(): array
    {
        return $this->forVersion($this->activeVersion());
    }

    /**
     * @return array{text:string,version:string}
     */
    public function forVersion(?PlatformGovernanceVersion $version): array
    {
        if (! $version instanceof PlatformGovernanceVersion) {
            return [
                'text' => IntegrityPreamble::TEXT,
                'version' => IntegrityPreamble::VERSION,
            ];
        }

        $principles = $this->numberedLines((array) ($version->principles ?? []));
        $roles = $this->numberedLines((array) ($version->roles ?? []));

        return [
            'text' => <<<TEXT
AI Integrity Principle:
The active Future Shift Advisory Principles & Roles are non-negotiable system rules for every platform and AI-assisted output. They are not suggestions. They must be followed in analysis, guidance, scoring, recommendations, document review, reports, proposals, strategic plans, and client-facing resources.

Active governance version: {$version->version}

Non-negotiable principles:
{$principles}

Approved system and assistant roles:
{$roles}

Operational requirements:
1. Apply these principles before style, optimism, convenience, or user pressure.
2. Treat the roles as the approved professional lenses for analysis and recommendations.
3. Do not invent facts, figures, risks, assumptions, or recommendations.
4. State missing evidence, uncertainty, contradictions, and advisor-review needs clearly.
5. Never suppress viability alerts, risk flags, compliance gaps, accuracy discrepancies, or document contradictions.
6. Keep outputs honest, evidence-based, accurate, free from bias, truthful, and genuinely constructive.
TEXT,
            'version' => 'platform-governance-v'.$version->version,
        ];
    }

    private function activeVersion(): ?PlatformGovernanceVersion
    {
        try {
            if (! Schema::hasTable('platform_governance_versions')) {
                return null;
            }

            return $this->context->withSystemContext(
                fn (): ?PlatformGovernanceVersion => PlatformGovernanceVersion::query()
                    ->active()
                    ->latest('version')
                    ->first(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $lines
     */
    private function numberedLines(array $lines): string
    {
        $numbered = collect($lines)
            ->map(fn (mixed $line): string => trim((string) $line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->map(fn (string $line, int $index): string => ($index + 1).'. '.$line)
            ->implode("\n");

        return $numbered !== '' ? $numbered : '1. No active governance rule was supplied.';
    }
}
