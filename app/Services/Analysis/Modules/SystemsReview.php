<?php

declare(strict_types=1);

namespace App\Services\Analysis\Modules;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

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
            'system_improvement_candidates' => $this->systemImprovementCandidates($client),
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
        $evidence = $this->evidence($client);
        $text = $this->evidenceText($evidence);
        $attributions = $this->sourceAttributions($client);
        $candidates = $this->systemImprovementCandidates($client);
        $integrationGap = $this->mentionsIntegrationGap($text);
        $upgradeGap = $this->mentionsUpgradeGap($text);
        $dataGap = $this->mentionsManualDataGap($text);
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
                body: $this->diagnosticBody($integrationGap, $upgradeGap, $dataGap, $candidates),
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
                body: $this->prescriptiveBody($candidates),
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
        return $this->standardAdvisoryResponses($client)
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

    /**
     * @return EloquentCollection<int, QuestionnaireResponse>
     */
    private function standardAdvisoryResponses(Client $client): EloquentCollection
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::STANDARD_ADVISORY))
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(3)
            ->get();
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

    /**
     * @param  array<int, array{value:mixed}>  $evidence
     */
    private function evidenceText(array $evidence): string
    {
        return strtolower(implode(' ', array_map(
            fn (array $item): string => $this->valueText($item['value']),
            $evidence,
        )));
    }

    private function valueText(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
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

    /**
     * @return array<int, array{category:string,title:string,business_case:string,fix:string,source_reference:string}>
     */
    private function systemImprovementCandidates(Client $client): array
    {
        $candidates = [];

        foreach ($this->evidence($client) as $item) {
            $value = $this->valueText($item['value']);
            $text = strtolower($value);
            $sourceReference = "questionnaire_answer:{$item['answer_id']}";

            if ($this->mentionsIntegrationGap($text)) {
                $candidates[$sourceReference.':integration'] = [
                    'category' => 'integration_gap',
                    'title' => $this->candidateTitle($value, 'System integration candidate'),
                    'business_case' => 'Quantify duplicate-entry time, reconciliation delay, stale reporting, and error cost across the handoff.',
                    'fix' => 'Map source system, destination system, trigger, data fields, error handling, and owner; replace re-keying with a governed sync or API handoff.',
                    'source_reference' => $sourceReference,
                ];
            }

            if ($this->mentionsManualDataGap($text)) {
                $candidates[$sourceReference.':manual_data'] = [
                    'category' => 'manual_data_workaround',
                    'title' => $this->candidateTitle($value, 'Manual data-workaround candidate'),
                    'business_case' => 'Quantify reporting lag, correction effort, duplicate data handling, and decision risk from inconsistent sources.',
                    'fix' => 'Choose the source of truth, remove duplicate-entry spreadsheets, and add validation checks before automating reports or operational dashboards.',
                    'source_reference' => $sourceReference,
                ];
            }

            if ($this->mentionsUpgradeGap($text)) {
                $candidates[$sourceReference.':upgrade'] = [
                    'category' => 'upgrade_gap',
                    'title' => $this->candidateTitle($value, 'System upgrade candidate'),
                    'business_case' => 'Quantify support risk, downtime exposure, security risk, licence waste, and growth constraint before replacement.',
                    'fix' => 'Sequence replacement by risk, data migration effort, integration dependency, training load, and operational downtime.',
                    'source_reference' => $sourceReference,
                ];
            }
        }

        return array_values($candidates);
    }

    /**
     * @param  array<int, array{category:string,title:string,business_case:string,fix:string,source_reference:string}>  $candidates
     */
    private function diagnosticBody(bool $integrationGap, bool $upgradeGap, bool $dataGap, array $candidates): string
    {
        $parts = [
            $integrationGap
                ? 'Integration-gap evidence is present and should be mapped by source system, destination system, failure point, and owner.'
                : 'Integration-gap evidence is not yet specific enough to prioritise.',
            $upgradeGap
                ? 'Legacy or upgrade evidence is present, indicating replacement sequencing should be assessed.'
                : 'Upgrade evidence is not yet specific enough to assess replacement urgency.',
            $dataGap
                ? 'Manual, spreadsheet, duplicate-entry, or data-quality evidence is present and should be targeted before adding more tooling.'
                : 'Data-quality or manual-workaround evidence is not yet specific enough to quantify.',
        ];

        if ($candidates !== []) {
            $parts[] = 'Named systems candidates: '.implode('; ', array_map(
                static fn (array $candidate): string => $candidate['title'].' - '.$candidate['business_case'].' '.$candidate['fix'],
                $candidates,
            ));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, array{category:string,title:string,business_case:string,fix:string,source_reference:string}>  $candidates
     */
    private function prescriptiveBody(array $candidates): string
    {
        if ($candidates === []) {
            return 'Prioritise integration mapping, source-of-truth decisions, manual-workaround removal, and upgrade sequencing before investing in new systems.';
        }

        return implode(' ', array_map(
            static fn (array $candidate): string => $candidate['title'].': '.$candidate['business_case'].' '.$candidate['fix'],
            $candidates,
        ));
    }

    private function mentionsIntegrationGap(string $text): bool
    {
        if ($this->contains($text, [
            'do not sync',
            'does not sync',
            'no sync',
            'not synced',
            'not integrated',
            'not fully integrated',
            'sync gap',
            'api missing',
            'manual export',
            'manual import',
            'double entry',
            'duplicate entry',
            're-key',
            'rekey',
        ])) {
            return true;
        }

        if ($this->contains($text, [
            'no integration gap',
            'sync automatically',
            'fully integrated',
            'systems are integrated',
            'integration is working',
            'api is working',
        ])) {
            return false;
        }

        if ($this->contains($text, ['integration gap'])) {
            return true;
        }

        return false;
    }

    private function mentionsManualDataGap(string $text): bool
    {
        $trimmed = trim($text);

        if (in_array($trimmed, ['none', 'n/a', 'not applicable'], true)) {
            return false;
        }

        if ($this->contains($text, [
            'no manual entry',
            'no manual task',
            'no manual tasks',
            'no manual work',
            'no manual workarounds',
            'no spreadsheets',
            'already automated',
            'fully automated',
            'single source of truth',
            'automated reports',
            'reporting is automated',
        ])) {
            return false;
        }

        return $this->contains($text, ['spreadsheet', 'manual', 'duplicate entry', 'duplicate-entry', 'double entry', 're-key', 'rekey', 'data quality', 'copy paste', 'copy/paste', 'manual report']);
    }

    private function mentionsUpgradeGap(string $text): bool
    {
        if ($this->contains($text, [
            'recently upgraded',
            'upgrade complete',
            'current system works',
            'current systems work',
            'no upgrade needed',
            'replacement not needed',
            'no replacement needed',
        ])) {
            return false;
        }

        if ($this->contains($text, [
            'legacy',
            'outdated',
            'replacement needed',
            'needs replacement',
            'replace',
            'upgrade plan needed',
            'upgrade needed',
            'unsupported',
            'end of life',
        ])) {
            return true;
        }

        return false;
    }

    private function candidateTitle(string $value, string $fallback): string
    {
        $line = collect(preg_split('/\r\n|\r|\n|[.;]/', $value) ?: [])
            ->map(static fn (string $part): string => trim($part))
            ->first(static fn (string $part): bool => $part !== '');

        if (! is_string($line) || $line === '') {
            return $fallback;
        }

        return Str::limit($line, 90, '');
    }
}
