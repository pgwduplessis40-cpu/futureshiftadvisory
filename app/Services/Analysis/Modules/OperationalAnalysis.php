<?php

declare(strict_types=1);

namespace App\Services\Analysis\Modules;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;

final class OperationalAnalysis implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.operational';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Operational;
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
            'operational_evidence' => $this->evidence($client),
            'analysis_dimensions' => ['sops', 'processes', 'bottlenecks', 'capacity', 'automation'],
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        return array_values(array_unique(array_map(
            static fn (array $attribution): string => $attribution['source_reference'],
            $this->sourceAttributions($client),
        )));
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        $text = $this->evidenceText($client);
        $attributions = $this->sourceAttributions($client);
        $hasBottleneck = $this->contains($text, ['bottleneck', 'delay', 'slow', 'waiting', 'rework']);
        $hasAutomation = $this->contains($text, ['automation', 'manual', 'spreadsheet', 'double entry']);
        $hasSopGap = $this->contains($text, ['sop', 'process', 'procedure', 'handover']);
        $severity = ($hasBottleneck || $hasAutomation || $hasSopGap) ? FindingSeverity::Medium : FindingSeverity::Low;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Operational evidence captured',
                body: sprintf('Operational analysis uses %d cited evidence item(s) covering SOPs, process flow, capacity, and automation signals.', count($this->evidence($client))),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Operational bottleneck diagnosis',
                body: $this->diagnosticBody($hasBottleneck, $hasAutomation, $hasSopGap),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $hasBottleneck ? FindingSeverity::Medium : FindingSeverity::Low,
                title: 'Operational capacity trajectory',
                body: $hasBottleneck
                    ? 'Current bottleneck evidence indicates capacity and delivery reliability will deteriorate as volume rises unless the process constraint is removed.'
                    : 'Capacity trajectory cannot be stress-tested until bottleneck and throughput evidence is quantified.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Operational improvement plan',
                body: 'Prioritise SOP clarity, bottleneck removal, handoff simplification, and automation of repeat manual steps before adding headcount or volume.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    /**
     * @return array<int, array{answer_id:int|string, prompt:string|null, value:mixed}>
     */
    private function evidence(Client $client): array
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(3)
            ->get()
            ->flatMap(function (QuestionnaireResponse $response): array {
                return $response->answers
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isOperationalAnswer($answer))
                    ->map(fn (QuestionnaireAnswer $answer): array => [
                        'answer_id' => $answer->id,
                        'prompt' => $answer->question?->prompt,
                        'value' => $answer->value,
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }

    private function isOperationalAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['operation', 'process', 'sop', 'bottleneck', 'capacity', 'workflow', 'automation', 'manual', 'handover'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(Client $client): array
    {
        $attributions = [];

        foreach ($this->evidence($client) as $item) {
            $attributions[] = [
                'claim' => 'Operational evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the operational analysis subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    private function evidenceText(Client $client): string
    {
        return strtolower(implode(' ', array_map(
            static fn (array $item): string => trim((string) $item['prompt'].' '.(is_array($item['value']) ? json_encode($item['value']) : $item['value'])),
            $this->evidence($client),
        )));
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function contains(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function diagnosticBody(bool $bottleneck, bool $automation, bool $sopGap): string
    {
        return implode(' ', [
            $bottleneck
                ? 'Bottleneck evidence is present and should be mapped to cycle time, queue time, and owner accountability.'
                : 'Bottleneck evidence is not yet specific enough to quantify throughput loss.',
            $automation
                ? 'Manual or automation evidence is present, indicating repeatable work should be assessed for automation value.'
                : 'Automation opportunity is not yet evidenced enough to prioritise.',
            $sopGap
                ? 'SOP or handover evidence is present, indicating process consistency risk.'
                : 'SOP evidence is not yet specific enough to assess consistency risk.',
        ]);
    }
}
