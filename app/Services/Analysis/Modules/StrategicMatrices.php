<?php

declare(strict_types=1);

namespace App\Services\Analysis\Modules;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Analysis\StrategicMatrixAssembler;
use App\Services\DataQuality\DataQualityScore;

final class StrategicMatrices implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.strategic_matrices';

    public function __construct(private readonly StrategicMatrixAssembler $matrices) {}

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Swot;
    }

    public function promptId(): string
    {
        return self::PROMPT_ID;
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
            ],
            'matrices' => $this->matrices->assemble($client),
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        $matrix = $this->matrices->assemble($client);

        return array_values(array_unique(array_map(
            static fn (array $attribution): string => $attribution['source_reference'],
            $matrix['attributions'],
        )));
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        $matrix = $this->matrices->assemble($client);
        $attributions = $matrix['attributions'];
        $pvLinkId = $matrix['pv']['top_improvement_id'] ?? $matrix['pv']['top_risk_id'] ?? null;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'SWOT matrix',
                body: $this->matrixBody('SWOT', $matrix['swot']),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::Medium,
                title: 'TOWS matrix',
                body: $this->matrixBody('TOWS', $matrix['tows']),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: FindingSeverity::Medium,
                title: 'MAPS matrix',
                body: $this->matrixBody('MAPS', $matrix['maps']),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: FindingSeverity::Medium,
                title: 'PV-referenced strategic priority',
                body: $this->pvBody($matrix['pv']),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
                pvLinkId: is_string($pvLinkId) ? $pvLinkId : null,
            ),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $matrix
     */
    private function matrixBody(string $label, array $matrix): string
    {
        $parts = [];

        foreach ($matrix as $key => $items) {
            $parts[] = strtoupper((string) $key).': '.implode(' ', $items);
        }

        return "{$label}: ".implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $pv
     */
    private function pvBody(array $pv): string
    {
        if (is_string($pv['top_improvement_title'] ?? null)) {
            return sprintf(
                'PV reference: prioritise improvement "%s" with current PV of NZD %s before sequencing lower-value strategic actions.',
                $pv['top_improvement_title'],
                number_format((float) ($pv['top_improvement_pv'] ?? 0), 0),
            );
        }

        if (is_string($pv['top_risk_title'] ?? null)) {
            return sprintf(
                'PV reference: prioritise risk "%s" with current PV exposure of NZD %s before sequencing lower-value strategic actions.',
                $pv['top_risk_title'],
                number_format((float) ($pv['top_risk_pv'] ?? 0), 0),
            );
        }

        return 'PV reference: no PV-ranked improvement or risk exists yet, so strategic priorities should be PV-quantified before release.';
    }
}
