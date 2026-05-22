<?php

declare(strict_types=1);

namespace App\Services\Analysis\Modules;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EconomicIndicator;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Analysis\HolidaysActLiabilityCalculator;
use App\Services\DataQuality\DataQualityScore;

final class HrAnalysis implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.hr';

    public function __construct(private readonly HolidaysActLiabilityCalculator $holidaysAct) {}

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Hr;
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
            'hr_evidence' => $this->hrEvidence($client),
            'verified_hr_documents' => $this->verifiedHrDocuments($client),
            'wage_benchmarks' => $this->wageBenchmarks(),
            'holidays_act_inputs' => $this->holidaysActInputs($client),
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
        $benchmarks = $this->wageBenchmarks();
        $hourlyRate = $this->hourlyRate($client);
        $minimumWage = $benchmarks['minimum_wage']['value'] ?? null;
        $livingWage = $benchmarks['living_wage']['value'] ?? null;
        $liability = $this->holidaysAct->calculate(...$this->holidaysActInputs($client));
        $documentSupport = $this->documentSupport($client);
        $attributions = $this->sourceAttributions($client);
        $wageSeverity = is_float($minimumWage) && $hourlyRate > 0 && $hourlyRate < $minimumWage
            ? FindingSeverity::High
            : FindingSeverity::Medium;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'HR evidence and staff structure',
                body: sprintf(
                    'HR analysis uses %d HR evidence item(s) and %d verified HR document(s), including CV, JD, staff-structure, or employment-record material where supplied.',
                    count($this->hrEvidence($client)),
                    count($this->verifiedHrDocuments($client)),
                ),
                attributions: $attributions,
                documentSupport: $documentSupport,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $wageSeverity,
                title: 'Wage compliance benchmark',
                body: $this->wageBody($hourlyRate, $minimumWage, $livingWage),
                attributions: $attributions,
                documentSupport: $documentSupport,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $liability['total_liability'] > 0 ? FindingSeverity::High : FindingSeverity::Low,
                title: 'Holidays Act liability exposure',
                body: sprintf(
                    'Estimated Holidays Act remediation exposure is %s, made up of %s gross underpayment plus %s remediation buffer.',
                    $this->money($liability['total_liability']),
                    $this->money($liability['gross_liability']),
                    $this->money($liability['remediation_buffer']),
                ),
                attributions: $attributions,
                documentSupport: $documentSupport,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: FindingSeverity::Medium,
                title: 'People remediation plan',
                body: 'Prioritise wage-rate correction, Holidays Act remediation validation, and CV/JD-to-role alignment before scaling staff structure or hiring plans.',
                attributions: $attributions,
                documentSupport: $documentSupport,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    /**
     * @return array<int, array{answer_id:int|string, prompt:string|null, value:mixed}>
     */
    private function hrEvidence(Client $client): array
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isHrAnswer($answer))
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
     * @return array<int, array{id:string, filename:string}>
     */
    private function verifiedHrDocuments(Client $client): array
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->where('category', Document::CATEGORY_HR_RECORD)
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->with('verifications')
            ->get()
            ->filter(fn (Document $document): bool => $document->verifications->isNotEmpty()
                && $document->verifications->every(
                    fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
                ))
            ->map(fn (Document $document): array => [
                'id' => (string) $document->id,
                'filename' => $document->original_filename,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function wageBenchmarks(): array
    {
        return EconomicIndicator::query()
            ->whereIn('indicator', [EconomicIndicator::MINIMUM_WAGE, EconomicIndicator::LIVING_WAGE])
            ->latest('period_date')
            ->latest('fetched_at')
            ->get()
            ->unique('indicator')
            ->mapWithKeys(fn (EconomicIndicator $indicator): array => [
                $indicator->indicator => [
                    'id' => $indicator->id,
                    'label' => $indicator->label,
                    'value' => $indicator->value,
                    'unit' => $indicator->unit,
                    'source_reference' => "economic_indicator:{$indicator->id}:{$indicator->indicator}",
                ],
            ])
            ->all();
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function holidaysActInputs(Client $client): array
    {
        $text = $this->evidenceText($client);
        preg_match('/(?:holidays act|holiday|underpaid).*?(\d+(?:\.\d+)?)\s*(?:hours|hrs).*?(?:at|rate)\s*\$?(\d+(?:\.\d+)?)/i', $text, $combined);

        if (isset($combined[1], $combined[2])) {
            return [(float) $combined[1], (float) $combined[2]];
        }

        preg_match('/(?:holidays act|holiday|underpaid)[^0-9]*(\d+(?:\.\d+)?)\s*(?:hours|hrs)/i', $text, $hours);
        preg_match('/(?:at|rate|hourly)[^0-9]*(\d+(?:\.\d+)?)/i', $text, $rate);

        return [
            isset($hours[1]) ? (float) $hours[1] : 0.0,
            isset($rate[1]) ? (float) $rate[1] : $this->hourlyRate($client),
        ];
    }

    private function hourlyRate(Client $client): float
    {
        $text = $this->evidenceText($client);
        preg_match('/(?:hourly rate|wage|pay rate|paid)[^0-9]*(\d+(?:\.\d+)?)/i', $text, $matches);

        return isset($matches[1]) ? (float) $matches[1] : 0.0;
    }

    private function isHrAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['hr', 'people', 'staff', 'employee', 'wage', 'holiday', 'holidays act', 'cv', 'jd', 'job description'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function evidenceText(Client $client): string
    {
        return implode(' ', array_map(
            static fn (array $item): string => trim((string) $item['prompt'].' '.(is_array($item['value']) ? json_encode($item['value']) : $item['value'])),
            $this->hrEvidence($client),
        ));
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(Client $client): array
    {
        $attributions = [];

        foreach ($this->hrEvidence($client) as $item) {
            $attributions[] = [
                'claim' => 'HR and people evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        foreach ($this->verifiedHrDocuments($client) as $document) {
            $attributions[] = [
                'claim' => 'HR document evidence has been verified for analysis use.',
                'source_reference' => "document:{$document['id']}",
            ];
        }

        foreach ($this->wageBenchmarks() as $benchmark) {
            $attributions[] = [
                'claim' => "{$benchmark['label']} benchmark is used for wage comparison.",
                'source_reference' => $benchmark['source_reference'],
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the HR analysis subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    private function documentSupport(Client $client): string
    {
        return $this->verifiedHrDocuments($client) === []
            ? AnalysisFinding::DOCUMENT_SUPPORT_NONE
            : AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED;
    }

    private function wageBody(float $hourlyRate, ?float $minimumWage, ?float $livingWage): string
    {
        $parts = ["Supplied hourly wage is {$this->money($hourlyRate)}."];

        if (is_float($minimumWage)) {
            $parts[] = $hourlyRate > 0 && $hourlyRate < $minimumWage
                ? "This is below the current minimum wage benchmark of {$this->money($minimumWage)}."
                : "This is at or above the current minimum wage benchmark of {$this->money($minimumWage)}.";
        }

        if (is_float($livingWage)) {
            $parts[] = $hourlyRate > 0 && $hourlyRate < $livingWage
                ? "It is below the living wage benchmark of {$this->money($livingWage)}."
                : "It is at or above the living wage benchmark of {$this->money($livingWage)}.";
        }

        return implode(' ', $parts);
    }

    private function money(float $value): string
    {
        return 'NZD '.number_format($value, 2);
    }
}
