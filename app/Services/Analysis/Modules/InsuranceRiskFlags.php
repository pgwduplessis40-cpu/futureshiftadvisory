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
use Carbon\Carbon;

final class InsuranceRiskFlags implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.insurance_risk';

    private const MIN_PUBLIC_LIABILITY = 1000000.0;

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::InsuranceRisk;
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
            'insurance_evidence' => $this->evidence($client),
            'verified_certificates' => $this->verifiedCertificates($client),
            'minimum_public_liability' => self::MIN_PUBLIC_LIABILITY,
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
        $flags = $this->flags($text);
        $attributions = $this->sourceAttributions($client);
        $support = $this->documentSupport($client);
        $severity = $flags === [] ? FindingSeverity::Low : FindingSeverity::High;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Insurance evidence captured',
                body: sprintf(
                    'Insurance risk review uses %d evidence item(s) and %d verified insurance certificate(s).',
                    count($this->evidence($client)),
                    count($this->verifiedCertificates($client)),
                ),
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Insurance coverage gaps',
                body: $flags === []
                    ? 'No material insurance coverage gap was detected from supplied evidence.'
                    : 'Insurance risk flags recorded for future broker referral: '.implode(' ', $flags),
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $severity,
                title: 'Insurance exposure trajectory',
                body: $flags === []
                    ? 'Insurance exposure trajectory appears stable from supplied certificate evidence, subject to renewal monitoring.'
                    : 'Insurance exposure may increase if coverage gaps, expired certificates, or low limits remain unresolved before referral or renewal.',
                attributions: $attributions,
                documentSupport: $support,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Insurance remediation actions',
                body: 'Confirm public liability, professional indemnity, key person, cyber/privacy, expiry dates, and coverage limits before any Phase 3 broker referral is generated.',
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isInsuranceAnswer($answer))
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
    private function verifiedCertificates(Client $client): array
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->where('category', Document::CATEGORY_INSURANCE_CERTIFICATE)
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
     * @return array<int, string>
     */
    private function flags(string $text): array
    {
        $flags = [];
        $amount = $this->publicLiabilityAmount($text);
        $expiry = $this->expiryDate($text);

        if (! str_contains($text, 'public liability')) {
            $flags[] = 'Public liability coverage is not evidenced.';
        } elseif ($amount !== null && $amount < self::MIN_PUBLIC_LIABILITY) {
            $flags[] = 'Public liability limit is below NZD '.number_format(self::MIN_PUBLIC_LIABILITY, 0).'.';
        }

        if (! str_contains($text, 'professional indemnity') || str_contains($text, 'no professional indemnity')) {
            $flags[] = 'Professional indemnity coverage is not evidenced.';
        }

        if (! str_contains($text, 'key person') || str_contains($text, 'no key person')) {
            $flags[] = 'Key person coverage is not evidenced.';
        }

        if ($expiry instanceof Carbon && $expiry->isPast()) {
            $flags[] = 'Insurance certificate appears expired.';
        }

        return $flags;
    }

    private function isInsuranceAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['insurance', 'coverage', 'certificate', 'policy', 'public liability', 'professional indemnity', 'key person'] as $needle) {
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
                'claim' => 'Insurance evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        foreach ($this->verifiedCertificates($client) as $document) {
            $attributions[] = [
                'claim' => 'Insurance certificate evidence has been verified for analysis use.',
                'source_reference' => "document:{$document['id']}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the insurance-risk subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    private function documentSupport(Client $client): string
    {
        return $this->verifiedCertificates($client) === []
            ? AnalysisFinding::DOCUMENT_SUPPORT_NONE
            : AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED;
    }

    private function evidenceText(Client $client): string
    {
        return strtolower(implode(' ', array_map(
            static fn (array $item): string => trim((string) $item['prompt'].' '.(is_array($item['value']) ? json_encode($item['value']) : $item['value'])),
            $this->evidence($client),
        )));
    }

    private function publicLiabilityAmount(string $text): ?float
    {
        preg_match('/public liability[^0-9]*(\d[\d,]*(?:\.\d+)?)/i', $text, $matches);

        return isset($matches[1]) ? (float) str_replace(',', '', $matches[1]) : null;
    }

    private function expiryDate(string $text): ?Carbon
    {
        preg_match('/(?:expiry|expires|expired)[^0-9]*(\d{4}-\d{2}-\d{2})/i', $text, $matches);

        return isset($matches[1]) ? Carbon::parse($matches[1]) : null;
    }
}
