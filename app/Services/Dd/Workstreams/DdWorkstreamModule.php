<?php

declare(strict_types=1);

namespace App\Services\Dd\Workstreams;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;
use App\Services\Dd\DataRoom;

final class DdWorkstreamModule implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.dd_workstream';

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $nzChecks
     */
    public function __construct(
        private readonly DdEngagement $engagement,
        private readonly string $workstream,
        private readonly array $evidence,
        private readonly array $nzChecks,
    ) {}

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::DdWorkstream;
    }

    public function promptId(): string
    {
        return self::PROMPT_ID;
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        return [
            'dd_engagement_id' => $this->engagement->id,
            'workstream' => $this->workstream,
            'workstream_label' => $this->label(),
            'target' => [
                'name' => $this->engagement->target_name,
                'details' => $this->engagement->target_details ?? [],
            ],
            'evidence' => $this->evidence,
            'nz_checks' => $this->nzChecks,
            'double_weighting' => [
                'verified_document_weight' => 2,
                'clean_unverified_document_weight' => 1,
                'actual_weight' => $this->evidence['verification_weight'] ?? 0,
            ],
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        return array_values(array_unique(array_merge(
            array_map(
                static fn (array $attribution): string => $attribution['source_reference'],
                $this->attributions(),
            ),
            $this->nzCheckSourceReferences(),
        )));
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        $attributions = $this->attributions();
        $support = (string) ($this->evidence['document_support'] ?? 'none');
        $weight = (int) ($this->evidence['verification_weight'] ?? 0);
        $itemCount = (int) ($this->evidence['item_count'] ?? 0);
        $verifiedCount = (int) ($this->evidence['verified_documents'] ?? 0);
        $severity = $weight > 0 ? FindingSeverity::Low : FindingSeverity::Medium;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: "{$this->label()} DD evidence spine",
                body: "The {$this->label()} DD workstream uses {$itemCount} data-room item(s); {$verifiedCount} verified document(s) are double-weighted, giving a document verification weight of {$weight}.",
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: "{$this->label()} NZ due-diligence checks",
                body: $this->nzCheckBody(),
                attributions: $this->withNzAttributions($attributions),
                documentSupport: $support,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: FindingSeverity::Medium,
                title: "{$this->label()} workstream next evidence request",
                body: "Before relying on the {$this->label()} DD conclusion, request missing primary evidence, reconcile it against the data room, and keep verified documents double-weighted over unsupported assertions.",
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    private function label(): string
    {
        return DataRoom::WORKSTREAMS[$this->workstream] ?? ucfirst(str_replace('_', ' ', $this->workstream));
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function attributions(): array
    {
        /** @var array<int, array{claim:string, source_reference:string}> $attributions */
        $attributions = $this->evidence['attributions'] ?? [];

        return $attributions === [] ? [[
            'claim' => 'DD engagement identifies the acquisition target.',
            'source_reference' => "dd_engagement:{$this->engagement->id}",
        ]] : $attributions;
    }

    /**
     * @param  array<int, array{claim:string, source_reference:string}>  $attributions
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function withNzAttributions(array $attributions): array
    {
        foreach ($this->nzCheckSourceReferences() as $reference) {
            $attributions[] = [
                'claim' => 'NZ due-diligence register or statute check was included.',
                'source_reference' => $reference,
            ];
        }

        return $attributions;
    }

    /**
     * @return array<int, string>
     */
    private function nzCheckSourceReferences(): array
    {
        $references = [];

        foreach ($this->nzChecks as $check) {
            if (is_array($check) && is_string($check['source_reference'] ?? null)) {
                $references[] = $check['source_reference'];
            }
        }

        return $references;
    }

    private function nzCheckBody(): string
    {
        if ($this->nzChecks === []) {
            return "No NZ-specific register or statute check is required for the {$this->label()} workstream at this stage; evidence still needs advisor review before release.";
        }

        $details = collect($this->nzChecks)
            ->map(function (mixed $check, string $key): string {
                if (! is_array($check)) {
                    return $key;
                }

                $finding = is_string($check['finding'] ?? null) ? $check['finding'] : null;
                $action = is_string($check['required_action'] ?? null) ? $check['required_action'] : null;

                return trim($key.': '.implode(' ', array_filter([$finding, $action])));
            })
            ->values()
            ->implode(' ');

        return 'NZ checks run for this workstream: '.implode(', ', array_keys($this->nzChecks)).'. '.$details;
    }
}
