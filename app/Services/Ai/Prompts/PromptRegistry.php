<?php

declare(strict_types=1);

namespace App\Services\Ai\Prompts;

use App\Services\Ai\Contracts\PromptEnvelope;
use InvalidArgumentException;

final class PromptRegistry
{
    /**
     * @var array<string, array{version:string, body:string, task:string}>
     */
    private array $prompts = [];

    public function __construct(private readonly ?GovernancePreambleProvider $governance = null)
    {
        $this->register(
            id: 'summarise.smoke',
            version: '2026-05-wo04',
            body: 'Summarise the supplied material without adding facts that are not in the input.',
            task: 'summarise',
        );

        $this->register(
            id: 'document.verify',
            version: '2026-05-wo18',
            body: 'Compare the supplied claim with the supplied document evidence. Return only JSON matching the schema, including metadata.verification_outcome as one of verified, advisory_flag, accuracy_discrepancy, or verification_error; metadata.confidence from 0 to 1; and metadata.client_explanation in plain English.',
            task: 'verify_document',
        );

        $this->register(
            id: 'quote_source.integration_extract',
            version: '2026-07-integration-quote-source-v1',
            body: 'Extract only explicit integration-scoping facts from the verified implementation-plan evidence and advisor description. Return draft systems, duplicate-entry tasks, and requested connections. Every draft row must name its document locator or description source and quote the supporting claim. Do not invent API capability, volume, timing, staffing, or costs; omit facts that cannot be evidenced.',
            task: 'quote_source_extract',
        );

        $this->register(
            id: 'analysis.financial',
            version: '2026-05-wo44',
            body: 'Analyse the client financial snapshot or questionnaire fallback across profitability, cash flow, drivers, ratios, root cause, and NZ economic overlay. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.website_audit',
            version: '2026-06-website-discoverability',
            body: 'Audit the client website evidence against what the client says it sells. Assess product/service content accuracy, SEO, GEO generative-engine extractability, AEO answer readiness, AIO AI-overview readiness, structured data, UX, calls to action, mobile performance, and New Zealand search visibility. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.competitor',
            version: '2026-05-wo46',
            body: 'Analyse up to six competitors for product, pricing, visibility, and strategic gaps. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.strategic_matrices',
            version: '2026-05-wo47',
            body: 'Generate SWOT, TOWS, and MAPS strategic matrices from the supplied client evidence and PV context. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.hr',
            version: '2026-05-wo48',
            body: 'Analyse HR and people evidence including CV/JD fit, staff structure, wage compliance, and Holidays Act liability. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.operational',
            version: '2026-05-wo49',
            body: 'Analyse operational evidence including SOPs, processes, bottlenecks, capacity constraints, and automation opportunities. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.systems',
            version: '2026-05-wo49',
            body: 'Analyse systems evidence including technology gaps, integration issues, manual workarounds, upgrade opportunities, and operational fit. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.compliance',
            version: '2026-05-wo50',
            body: 'Check New Zealand compliance evidence against ERA, Health and Safety at Work Act, Holidays Act, Privacy Act, and Companies Act obligations. Return severity-rated, legislatively current findings with statute citations.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.insurance_risk',
            version: '2026-05-wo52',
            body: 'Detect insurance coverage gaps from client evidence and uploaded certificates. Verify coverage type, amount, and expiry evidence. Return evidence-based insurance risk flags only.',
            task: 'analyse',
        );

        $this->register(
            id: 'npo.governance_review.analysis',
            version: '2026-05-wo-n04',
            body: 'Assess NPO governance evidence against the legal-structure-specific criteria supplied in the input. Return source-attributed findings only, disclose uncertainty where evidence is thin, avoid score inflation, and keep all outputs pending advisor review.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.dd_workstream',
            version: '2026-05-wo77',
            body: 'Analyse one due diligence workstream using the DD data-room evidence, double-weight verified document support, and New Zealand register/compliance checks. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );
    }

    public function register(string $id, string $version, string $body, string $task = 'analyse'): void
    {
        $this->prompts[$id] = [
            'version' => $version,
            'body' => $body,
            'task' => $task,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $dataQualitySummary
     * @param  array<int, string>  $sourceReferences
     */
    public function envelope(
        string $id,
        array $input = [],
        array $dataQualitySummary = [],
        array $sourceReferences = [],
    ): PromptEnvelope {
        if (! isset($this->prompts[$id])) {
            throw new InvalidArgumentException("Prompt [{$id}] is not registered.");
        }

        $prompt = $this->prompts[$id];
        $governance = $this->governancePreamble();

        return new PromptEnvelope(
            id: $id,
            version: $prompt['version'],
            task: $prompt['task'],
            body: $prompt['body'],
            input: $input,
            dataQualitySummary: $dataQualitySummary,
            sourceReferences: $sourceReferences,
            integrityPreamble: $governance['text'],
            integrityPreambleVersion: $governance['version'],
        );
    }

    public function promptHash(string $id): string
    {
        return $this->envelope($id)->hash();
    }

    /**
     * @return array{text:string,version:string}
     */
    private function governancePreamble(): array
    {
        return $this->governance?->active() ?? [
            'text' => IntegrityPreamble::TEXT,
            'version' => IntegrityPreamble::VERSION,
        ];
    }
}
