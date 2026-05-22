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

    public function __construct()
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
            id: 'analysis.financial',
            version: '2026-05-wo44',
            body: 'Analyse the client financial snapshot or questionnaire fallback across profitability, cash flow, drivers, ratios, root cause, and NZ economic overlay. Return evidence-based findings only and cite every factual claim.',
            task: 'analyse',
        );

        $this->register(
            id: 'analysis.website_audit',
            version: '2026-05-wo45',
            body: 'Audit the client website evidence for SEO, content clarity, UX, calls to action, mobile performance, and New Zealand search visibility. Return evidence-based findings only and cite every factual claim.',
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

        return new PromptEnvelope(
            id: $id,
            version: $prompt['version'],
            task: $prompt['task'],
            body: $prompt['body'],
            input: $input,
            dataQualitySummary: $dataQualitySummary,
            sourceReferences: $sourceReferences,
        );
    }

    public function promptHash(string $id): string
    {
        return $this->envelope($id)->hash();
    }
}
