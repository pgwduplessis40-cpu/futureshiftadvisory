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

final class WebsiteAudit implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.website_audit';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::WebsiteAudit;
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
            'website_evidence' => $this->websiteEvidence($client),
            'audit_scope' => [
                'seo',
                'content',
                'ux',
                'calls_to_action',
                'mobile_performance',
                'nz_search_visibility',
            ],
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
        $evidence = $this->websiteEvidence($client);
        $attributions = $this->sourceAttributions($client);
        $text = strtolower(implode(' ', array_map(
            static fn (array $item): string => (string) $item['value'],
            $evidence,
        )));

        $hasWebsite = str_contains($text, 'http') || str_contains($text, 'www') || str_contains($text, '.co.nz') || str_contains($text, '.nz');
        $mentionsMobileIssue = str_contains($text, 'mobile') && (str_contains($text, 'slow') || str_contains($text, 'poor') || str_contains($text, 'not responsive'));
        $mentionsCtaIssue = str_contains($text, 'cta') || str_contains($text, 'call to action') || str_contains($text, 'enquiry');
        $mentionsNzRanking = str_contains($text, 'nz') || str_contains($text, 'new zealand') || str_contains($text, 'local search');
        $severity = ($mentionsMobileIssue || $mentionsCtaIssue) ? FindingSeverity::Medium : FindingSeverity::Low;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Website audit evidence captured',
                body: sprintf(
                    'Website audit evidence includes %d questionnaire item(s)%s.',
                    count($evidence),
                    $hasWebsite ? ' with a nominated website or domain' : ' without a confirmed website URL',
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'SEO, content, UX, and CTA gaps',
                body: $this->diagnosticBody($mentionsMobileIssue, $mentionsCtaIssue, $mentionsNzRanking),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $severity,
                title: 'NZ search visibility risk',
                body: $mentionsNzRanking
                    ? 'The website evidence includes New Zealand or local-search context, so ranking checks should focus on NZ-intent service terms and local conversion pages.'
                    : 'The website evidence does not yet identify NZ search terms or local landing pages, which limits confidence in future lead-generation trajectory.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Website audit action plan',
                body: 'Prioritise mobile responsiveness, clearer service-page content, NZ-local search terms, and visible enquiry calls to action before treating the website as a reliable advisory growth channel.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    /**
     * @return array<int, array{response_id:string, answer_id:int|string, prompt:string|null, value:mixed}>
     */
    private function websiteEvidence(Client $client): array
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isWebsiteAnswer($answer))
                    ->map(fn (QuestionnaireAnswer $answer): array => [
                        'response_id' => (string) $response->id,
                        'answer_id' => $answer->id,
                        'prompt' => $answer->question?->prompt,
                        'value' => $answer->value,
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }

    private function isWebsiteAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['website', 'seo', 'mobile', 'search', 'cta', 'call to action', 'landing page', 'enquiry'] as $needle) {
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

        foreach ($this->websiteEvidence($client) as $item) {
            $attributions[] = [
                'claim' => 'Website audit evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the website audit subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    private function diagnosticBody(bool $mobileIssue, bool $ctaIssue, bool $nzRanking): string
    {
        $parts = [
            'Audit focus areas are SEO metadata, service-page content clarity, user journey friction, conversion calls to action, and mobile usability.',
        ];

        $parts[] = $mobileIssue
            ? 'The supplied evidence flags mobile performance or responsiveness as a likely conversion constraint.'
            : 'No explicit mobile-performance issue is supplied, so mobile speed and responsiveness still need measurement before release decisions.';

        $parts[] = $ctaIssue
            ? 'The supplied evidence flags enquiry or CTA clarity as a conversion risk.'
            : 'CTA strength is not evidenced yet, so the next review should confirm whether enquiry actions are obvious above the fold and at service-page decision points.';

        $parts[] = $nzRanking
            ? 'NZ search context is present and should be checked against local service-intent queries.'
            : 'NZ search ranking context is missing and should be added before benchmarking visibility.';

        return implode(' ', $parts);
    }
}
