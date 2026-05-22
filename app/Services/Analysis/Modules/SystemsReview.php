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

final class SystemsReview implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.systems';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Systems;
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
            'systems_evidence' => $this->evidence($client),
            'analysis_dimensions' => ['technology_gaps', 'integrations', 'manual_workarounds', 'upgrade_opportunities'],
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
        $integrationGap = $this->contains($text, ['integration', 'sync', 'api', 'double entry']);
        $upgradeGap = $this->contains($text, ['legacy', 'upgrade', 'outdated', 'replacement']);
        $dataGap = $this->contains($text, ['spreadsheet', 'manual', 'duplicate', 'data quality']);
        $severity = ($integrationGap || $upgradeGap || $dataGap) ? FindingSeverity::Medium : FindingSeverity::Low;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Systems evidence captured',
                body: sprintf('Systems review uses %d cited evidence item(s) covering technology, integrations, data flow, and upgrade signals.', count($this->evidence($client))),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Systems and integration gaps',
                body: $this->diagnosticBody($integrationGap, $upgradeGap, $dataGap),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $integrationGap ? FindingSeverity::Medium : FindingSeverity::Low,
                title: 'Systems scalability trajectory',
                body: $integrationGap
                    ? 'Integration-gap evidence indicates rework, reporting delay, and data-quality risk will grow as transaction volume rises.'
                    : 'Systems scalability cannot be projected confidently until integration and data-flow evidence is quantified.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Systems upgrade plan',
                body: 'Prioritise integration mapping, source-of-truth decisions, manual-workaround removal, and upgrade sequencing before investing in new systems.',
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isSystemsAnswer($answer))
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

    private function isSystemsAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['system', 'software', 'technology', 'integration', 'crm', 'erp', 'api', 'spreadsheet', 'manual', 'upgrade'] as $needle) {
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
                'claim' => 'Systems-review evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the systems-review subject.',
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

    private function diagnosticBody(bool $integrationGap, bool $upgradeGap, bool $dataGap): string
    {
        return implode(' ', [
            $integrationGap
                ? 'Integration-gap evidence is present and should be mapped by source system, destination system, failure point, and owner.'
                : 'Integration-gap evidence is not yet specific enough to prioritise.',
            $upgradeGap
                ? 'Legacy or upgrade evidence is present, indicating replacement sequencing should be assessed.'
                : 'Upgrade evidence is not yet specific enough to assess replacement urgency.',
            $dataGap
                ? 'Manual, spreadsheet, duplicate-entry, or data-quality evidence is present and should be targeted before adding more tooling.'
                : 'Data-quality or manual-workaround evidence is not yet specific enough to quantify.',
        ]);
    }
}
