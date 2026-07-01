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
            'automation_candidates' => $this->automationCandidates($client),
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
        $evidence = $this->evidence($client);
        $text = $this->evidenceText($evidence);
        $attributions = $this->sourceAttributions($client);
        $candidates = $this->automationCandidates($client);
        $hasBottleneck = $this->mentionsBottleneck($text);
        $hasAutomation = $candidates !== [];
        $hasSopGap = $this->mentionsSopGap($evidence);
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
                body: $this->diagnosticBody($hasBottleneck, $hasAutomation, $hasSopGap, $candidates),
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
                body: $this->prescriptiveBody($candidates, $hasBottleneck, $hasSopGap),
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
    private function automationCandidates(Client $client): array
    {
        $candidates = [];

        foreach ($this->evidence($client) as $item) {
            $prompt = strtolower((string) $item['prompt']);
            $value = $this->valueText($item['value']);
            $text = strtolower($value);
            $sourceReference = "questionnaire_answer:{$item['answer_id']}";

            if ($this->mentionsManualWork($text)) {
                $candidates[$sourceReference.':manual'] = [
                    'category' => 'manual_work',
                    'title' => $this->candidateTitle($value, 'Manual-work automation candidate'),
                    'business_case' => 'Quantify annual labour cost as frequency x time per run x loaded hourly rate, then add rework, error, and customer-delay cost.',
                    'fix' => 'Map trigger, owner, frequency, time per run, exception path, and system handoff; then automate the repeatable step before adding headcount.',
                    'source_reference' => $sourceReference,
                ];
            }

            if ($this->mentionsBottleneck($text)) {
                $candidates[$sourceReference.':bottleneck'] = [
                    'category' => 'bottleneck',
                    'title' => $this->candidateTitle($value, 'Operational bottleneck candidate'),
                    'business_case' => 'Quantify throughput loss, delayed revenue, overtime, rework, and customer-impact cost before choosing the fix.',
                    'fix' => 'Measure cycle time, queue time, rework rate, and owner accountability; remove or automate the constraint with the highest throughput impact first.',
                    'source_reference' => $sourceReference,
                ];
            }

            if ($this->mentionsSopGapInAnswer($prompt, $text)) {
                $candidates[$sourceReference.':sop'] = [
                    'category' => 'sop_gap',
                    'title' => $this->candidateTitle($value, 'SOP and handover candidate'),
                    'business_case' => 'Quantify rework, training time, owner dependency, and control failures caused by undocumented or inconsistent execution.',
                    'fix' => 'Document the current process, assign an owner, define control checks, and convert repeated handoffs into a checklist or workflow.',
                    'source_reference' => $sourceReference,
                ];
            }
        }

        return array_values($candidates);
    }

    /**
     * @param  array<int, array{category:string,title:string,business_case:string,fix:string,source_reference:string}>  $candidates
     */
    private function diagnosticBody(bool $bottleneck, bool $automation, bool $sopGap, array $candidates): string
    {
        $parts = [
            $bottleneck
                ? 'Bottleneck evidence is present and should be mapped to cycle time, queue time, and owner accountability.'
                : 'Bottleneck evidence is not yet specific enough to quantify throughput loss.',
            $automation
                ? 'Manual or automation evidence is present, indicating repeatable work should be assessed for automation value.'
                : 'Automation opportunity is not yet evidenced enough to prioritise.',
            $sopGap
                ? 'SOP or handover evidence is present, indicating process consistency risk.'
                : 'SOP evidence is not yet specific enough to assess consistency risk.',
        ];

        if ($candidates !== []) {
            $parts[] = 'Named automation candidates: '.implode('; ', array_map(
                static fn (array $candidate): string => $candidate['title'].' - '.$candidate['business_case'].' '.$candidate['fix'],
                $candidates,
            ));
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, array{category:string,title:string,business_case:string,fix:string,source_reference:string}>  $candidates
     */
    private function prescriptiveBody(array $candidates, bool $bottleneck, bool $sopGap): string
    {
        if ($candidates === []) {
            return 'Prioritise SOP clarity, bottleneck removal, handoff simplification, and automation of repeat manual steps before adding headcount or volume.';
        }

        $parts = array_map(
            static fn (array $candidate): string => $candidate['title'].': '.$candidate['business_case'].' '.$candidate['fix'],
            $candidates,
        );

        if ($bottleneck) {
            $parts[] = 'Sequence fixes by the bottleneck with the highest throughput, delay, rework, or customer-impact cost.';
        }

        if ($sopGap) {
            $parts[] = 'Stabilise the process with a lightweight SOP before automating, so the workflow does not preserve an unclear handoff.';
        }

        return implode(' ', $parts);
    }

    private function mentionsBottleneck(string $text): bool
    {
        if ($this->contains($text, ['no bottleneck', 'no bottlenecks', 'no delays', 'not slow', 'no rework'])) {
            return false;
        }

        return $this->contains($text, ['bottleneck', 'delay', 'delays', 'slow', 'waiting', 'queue', 'rework', 'stuck']);
    }

    private function mentionsManualWork(string $text): bool
    {
        $trimmed = trim($text);

        if (in_array($trimmed, ['none', 'n/a', 'not applicable'], true)) {
            return false;
        }

        if ($this->contains($text, [
            'no manual task',
            'no manual tasks',
            'no manual work',
            'manual tasks: none',
            'manual work: none',
            'no repetitive task',
            'no repetitive work',
            'already automated',
            'fully automated',
            'automation already',
        ])) {
            return false;
        }

        return $this->contains($text, [
            'manual',
            'spreadsheet',
            'double entry',
            'duplicate entry',
            're-key',
            'rekey',
            'copy paste',
            'copy/paste',
            'repetitive',
            'repeat',
            'slow',
            'hours per week',
            'takes a lot of time',
            'could be automated',
            'not automated',
            'needs automation',
        ]);
    }

    /**
     * @param  array<int, array{prompt:string|null,value:mixed}>  $evidence
     */
    private function mentionsSopGap(array $evidence): bool
    {
        foreach ($evidence as $item) {
            if ($this->mentionsSopGapInAnswer(
                strtolower((string) $item['prompt']),
                strtolower($this->valueText($item['value'])),
            )) {
                return true;
            }
        }

        return false;
    }

    private function mentionsSopGapInAnswer(string $prompt, string $text): bool
    {
        if (! $this->contains($prompt.' '.$text, ['sop', 'standard operating', 'process', 'procedure', 'handover'])) {
            return false;
        }

        $trimmed = trim($text);

        if (in_array($trimmed, ['no', 'none', 'partial', 'partially'], true)) {
            return true;
        }

        return $this->contains($text, [
            'no sop',
            'no standard operating',
            'no written',
            'not documented',
            'not written',
            'undocumented',
            'inconsistent',
            'informal',
            'ad hoc',
            'tribal',
            'handover gap',
            'key person',
        ]);
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
