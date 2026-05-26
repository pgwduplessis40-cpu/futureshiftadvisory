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
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;

final class ComplianceChecker implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.compliance';

    public const HOLIDAYS_ACT = 'Holidays Act 2003';

    /**
     * @var array<string, string>
     */
    private array $statutes = [
        'era' => 'Employment Relations Act 2000',
        'hswa' => 'Health and Safety at Work Act 2015',
        'holidays' => self::HOLIDAYS_ACT,
        'privacy' => 'Privacy Act 2020',
        'companies' => 'Companies Act 1993',
    ];

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Compliance;
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
            'statutes' => $this->statutes,
            'compliance_evidence' => $this->evidence($client),
            'verified_compliance_documents' => $this->verifiedDocuments($client),
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
        $support = $this->documentSupport($client);
        $severity = $this->severity($text);

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'NZ compliance evidence captured',
                body: sprintf(
                    'Compliance review covers ERA, Health and Safety, Holidays Act, Privacy Act, and Companies Act signals using %d evidence item(s) and %d verified compliance document(s).',
                    count($this->evidence($client)),
                    count($this->verifiedDocuments($client)),
                ),
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Compliance severity assessment',
                body: $this->severityBody($text),
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $severity,
                title: 'Legislative currency exposure',
                body: 'Compliance obligations should be reviewed against current NZ legislative sources before advice is released, especially where employment, H&S, payroll, privacy, or director-duty evidence is incomplete.',
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Compliance action priorities',
                body: 'Prioritise employment-agreement checks, H&S policy evidence, Holidays Act/payroll validation, privacy-breach process confirmation, and Companies Act director-record completeness.',
                attributions: $attributions,
                documentSupport: $support,
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isComplianceAnswer($answer))
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
    private function verifiedDocuments(Client $client): array
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->whereIn('category', [
                Document::CATEGORY_CONTRACT,
                Document::CATEGORY_HR_RECORD,
                Document::CATEGORY_COMPLIANCE_DOC,
                Document::CATEGORY_INSURANCE_CERTIFICATE,
            ])
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

    private function isComplianceAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['compliance', 'employment agreement', 'health and safety', 'h&s', 'holiday', 'payroll', 'privacy', 'companies act', 'director'] as $needle) {
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
                'claim' => 'Compliance evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        foreach ($this->verifiedDocuments($client) as $document) {
            $attributions[] = [
                'claim' => 'Compliance document evidence has been verified for analysis use.',
                'source_reference' => "document:{$document['id']}",
            ];
        }

        foreach ($this->statutes as $key => $statute) {
            $attributions[] = [
                'claim' => "{$statute} is in the compliance checker statute set.",
                'source_reference' => "statute:nz:{$key}",
            ];
        }

        return $attributions;
    }

    private function documentSupport(Client $client): string
    {
        return $this->verifiedDocuments($client) === []
            ? AnalysisFinding::DOCUMENT_SUPPORT_NONE
            : AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED;
    }

    private function severity(string $text): FindingSeverity
    {
        if (str_contains($text, 'breach') || str_contains($text, 'missing') || str_contains($text, 'expired')) {
            return FindingSeverity::High;
        }

        if (str_contains($text, 'unclear') || str_contains($text, 'needs review')) {
            return FindingSeverity::Medium;
        }

        return FindingSeverity::Low;
    }

    private function severityBody(string $text): string
    {
        $parts = [];
        $parts[] = str_contains($text, 'employment') || str_contains($text, 'era')
            ? 'Employment Relations Act evidence is present and should be checked against signed employment agreement records.'
            : 'Employment Relations Act evidence is incomplete.';
        $parts[] = str_contains($text, 'health') || str_contains($text, 'h&s')
            ? 'Health and Safety at Work Act evidence is present and should be cross-checked against the H&S policy.'
            : 'Health and Safety at Work Act evidence is incomplete.';
        $parts[] = str_contains($text, 'holiday') || str_contains($text, 'payroll')
            ? 'Holidays Act/payroll evidence is present and should be checked for remediation exposure.'
            : 'Holidays Act/payroll evidence is incomplete.';
        $parts[] = str_contains($text, 'privacy')
            ? 'Privacy Act evidence is present and should be checked for breach-response readiness.'
            : 'Privacy Act evidence is incomplete.';
        $parts[] = str_contains($text, 'director') || str_contains($text, 'companies')
            ? 'Companies Act evidence is present and should be checked against director and company records.'
            : 'Companies Act evidence is incomplete.';

        return implode(' ', $parts);
    }

    private function evidenceText(Client $client): string
    {
        return strtolower(implode(' ', array_map(
            static fn (array $item): string => trim((string) $item['prompt'].' '.(is_array($item['value']) ? json_encode($item['value']) : $item['value'])),
            $this->evidence($client),
        )));
    }
}
