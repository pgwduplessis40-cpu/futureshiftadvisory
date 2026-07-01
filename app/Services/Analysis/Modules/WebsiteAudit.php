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
                'trading_name' => $client->trading_name,
            ],
            'website_evidence' => $this->websiteEvidence($client),
            'product_service_evidence' => $this->productServiceEvidence($client),
            'audit_scope' => [
                'product_service_content_alignment',
                'seo',
                'geo_generative_engine_optimisation',
                'aeo_answer_engine_optimisation',
                'aio_ai_overview_optimisation',
                'structured_data_extractability',
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
        $websiteEvidence = $this->websiteEvidence($client);
        $productServiceEvidence = $this->productServiceEvidence($client);
        $evidence = [...$websiteEvidence, ...$productServiceEvidence];
        $attributions = $this->sourceAttributions($client);
        $text = $this->evidenceText($evidence);
        $websiteText = $this->evidenceText($websiteEvidence);

        $hasWebsite = str_contains($text, 'http') || str_contains($text, 'www') || str_contains($text, '.co.nz') || str_contains($text, '.nz');
        $mentionsMobileIssue = $this->mentionsMobileIssue($text);
        $mentionsMobileEvidence = $this->mentionsMobileEvidence($text);
        $mentionsCtaIssue = $this->mentionsCtaIssue($text);
        $mentionsCtaEvidence = $this->mentionsCtaEvidence($text);
        $mentionsNzRanking = str_contains($text, 'nz') || str_contains($text, 'new zealand') || str_contains($text, 'local search');
        $hasProductServiceEvidence = $productServiceEvidence !== [];
        $alignmentIsEvidenced = $this->alignmentIsEvidenced($websiteText, $productServiceEvidence);
        $mentionsDiscoverability = $this->mentionsDiscoverabilityEvidence($text);
        $discoverabilityGap = $this->mentionsDiscoverabilityGap($text);
        $severity = ($mentionsMobileIssue || $mentionsCtaIssue || ! $alignmentIsEvidenced || $discoverabilityGap || ! $mentionsDiscoverability)
            ? FindingSeverity::Medium
            : FindingSeverity::Low;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Website audit evidence captured',
                body: sprintf(
                    'Website audit evidence includes %d website item(s) and %d product/service item(s)%s.',
                    count($websiteEvidence),
                    count($productServiceEvidence),
                    $hasWebsite ? ' with a nominated website or domain' : ' without a confirmed website URL',
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Product/service alignment and discoverability gaps',
                body: $this->diagnosticBody(
                    mobileIssue: $mentionsMobileIssue,
                    mobileIsEvidenced: $mentionsMobileEvidence,
                    ctaIssue: $mentionsCtaIssue,
                    ctaIsEvidenced: $mentionsCtaEvidence,
                    nzRanking: $mentionsNzRanking,
                    hasProductServiceEvidence: $hasProductServiceEvidence,
                    alignmentIsEvidenced: $alignmentIsEvidenced,
                    discoverabilityIsEvidenced: $mentionsDiscoverability,
                    discoverabilityGap: $discoverabilityGap,
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $severity,
                title: 'SEO, GEO, AEO, and AIO visibility risk',
                body: ($mentionsNzRanking || $mentionsDiscoverability)
                    ? 'The website evidence includes search or local-search context, so visibility checks should focus on NZ-intent product/service terms and whether page content is extractable for SEO, GEO, AEO, and AIO surfaces.'
                    : 'The website evidence does not yet identify NZ search terms, answer-ready service summaries, structured data, or AI-readable product/service pages, which limits confidence in future lead-generation trajectory.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Website audit action plan',
                body: 'Prioritise accurate product/service page copy, clear headings and metadata, structured data, FAQ or answer blocks, NZ-local search terms, mobile responsiveness, and visible enquiry calls to action before treating the website as a reliable advisory growth channel.',
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
        return $this->standardAdvisoryResponses($client)
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

    /**
     * @return array<int, array{response_id:string, answer_id:int|string, prompt:string|null, value:mixed}>
     */
    private function productServiceEvidence(Client $client): array
    {
        return $this->standardAdvisoryResponses($client)
            ->flatMap(function (QuestionnaireResponse $response): array {
                return $response->answers
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isProductServiceAnswer($answer))
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

    private function isWebsiteAnswer(QuestionnaireAnswer $answer): bool
    {
        $haystack = $this->answerHaystack($answer);

        foreach (['website', 'seo', 'geo', 'aeo', 'aio', 'mobile', 'search', 'schema', 'structured data', 'answer engine', 'generative engine', 'ai overview', 'ai search', 'cta', 'call to action', 'landing page', 'product page', 'service page', 'enquiry'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isProductServiceAnswer(QuestionnaireAnswer $answer): bool
    {
        $haystack = $this->answerHaystack($answer);

        foreach (['product', 'products', 'service', 'services', 'selling', 'sells', 'sold', 'offer', 'offers', 'price', 'pricing', 'package', 'packages', 'customer', 'customers', 'sales channel'] as $needle) {
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
            $attributions["questionnaire_answer:{$item['answer_id']}"] = [
                'claim' => 'Website audit evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        foreach ($this->productServiceEvidence($client) as $item) {
            $attributions["questionnaire_answer:{$item['answer_id']}"] ??= [
                'claim' => 'Product/service evidence comes from the submitted questionnaire.',
                'source_reference' => "questionnaire_answer:{$item['answer_id']}",
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the website audit subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return array_values($attributions);
    }

    private function diagnosticBody(
        bool $mobileIssue,
        bool $mobileIsEvidenced,
        bool $ctaIssue,
        bool $ctaIsEvidenced,
        bool $nzRanking,
        bool $hasProductServiceEvidence,
        bool $alignmentIsEvidenced,
        bool $discoverabilityIsEvidenced,
        bool $discoverabilityGap,
    ): string {
        $parts = [
            'Audit focus areas are whether the website supports the products and services the client says it sells, whether those pages are clear enough for buyers, and whether the page content is aligned for SEO, GEO generative-engine extractability, AEO answer readiness, AIO AI-overview readiness, conversion calls to action, and mobile usability.',
        ];

        $parts[] = $hasProductServiceEvidence
            ? 'Product/service evidence is present, so website pages should be checked against the actual offers, pricing or package cues, audience, benefits, proof points, and conversion path.'
            : 'Core product/service evidence is missing, so content alignment cannot be verified until the client states what they sell and to whom.';

        $parts[] = $alignmentIsEvidenced
            ? 'The website evidence appears to support at least part of the stated product/service offer, but the advisor should still confirm that page copy, headings, proof points, and conversion paths match what the client actually sells.'
            : 'The supplied website evidence does not yet prove that page content accurately names, explains, and supports the products or services the client says it sells.';

        $parts[] = $mobileIssue
            ? 'The supplied evidence flags mobile performance or responsiveness as a likely conversion constraint.'
            : ($mobileIsEvidenced
                ? 'The supplied evidence describes mobile speed or responsiveness positively, but mobile performance should still be measured before release decisions.'
                : 'No explicit mobile-performance issue is supplied, so mobile speed and responsiveness still need measurement before release decisions.');

        $parts[] = $ctaIssue
            ? 'The supplied evidence flags enquiry or CTA clarity as a conversion risk.'
            : ($ctaIsEvidenced
                ? 'The supplied evidence describes enquiry or CTA visibility positively, but the advisor should still confirm whether enquiry actions are obvious above the fold and at service-page decision points.'
                : 'CTA strength is not evidenced yet, so the next review should confirm whether enquiry actions are obvious above the fold and at service-page decision points.');

        $parts[] = $nzRanking
            ? 'NZ search context is present and should be checked against local service-intent queries.'
            : 'NZ search ranking context is missing and should be added before benchmarking visibility.';

        $parts[] = $discoverabilityGap
            ? 'The supplied evidence flags missing or weak metadata, schema, structured data, FAQ or answer blocks, concise service definitions, or AI-readable proof points for SEO, GEO, AEO, and AIO pickup.'
            : ($discoverabilityIsEvidenced
            ? 'The supplied evidence mentions search, structured data, answer-engine, or AI-search signals, so the review should test whether engines can extract concise products, services, locations, FAQs, and proof points for SEO, GEO, AEO, and AIO surfaces.'
            : 'The supplied evidence does not yet show metadata, schema or structured data, FAQ or answer blocks, concise service definitions, or AI-readable proof points for SEO, GEO, AEO, and AIO pickup.');

        return implode(' ', $parts);
    }

    private function mentionsMobileIssue(string $text): bool
    {
        return $this->containsAny($text, [
            'mobile pages are slow',
            'mobile page is slow',
            'mobile is slow',
            'slow mobile',
            'poor mobile',
            'not responsive',
            'mobile performance issue',
            'mobile performance problem',
            'mobile pages need work',
            'mobile pages are weak',
            'mobile pages are poor',
        ]);
    }

    private function mentionsMobileEvidence(string $text): bool
    {
        return $this->containsAny($text, [
            'mobile pages are fast',
            'mobile page is fast',
            'mobile is fast',
            'fast mobile',
            'mobile pages are responsive',
            'mobile page is responsive',
            'mobile is responsive',
            'fast and responsive',
            'responsive and fast',
        ]);
    }

    private function mentionsCtaIssue(string $text): bool
    {
        return $this->containsAny($text, [
            'cta is unclear',
            'cta unclear',
            'unclear cta',
            'weak cta',
            'missing cta',
            'hidden cta',
            'call to action is unclear',
            'unclear call to action',
            'weak call to action',
            'missing call to action',
            'enquiry cta is unclear',
            'enquiry is unclear',
            'hard to enquire',
            'hard to find the enquiry',
            'not obvious',
            'below the fold',
        ]);
    }

    private function mentionsCtaEvidence(string $text): bool
    {
        return $this->containsAny($text, [
            'cta is clear',
            'clear cta',
            'visible cta',
            'call to action is clear',
            'clear call to action',
            'visible call to action',
            'enquiry cta is clear',
            'enquiry is clear',
            'clear enquiry',
            'visible enquiry',
            'above the fold',
        ]);
    }

    private function mentionsDiscoverabilityEvidence(string $text): bool
    {
        return $this->containsAny($text, [
            'seo',
            'search',
            'metadata',
            'meta description',
            'title tag',
            'schema',
            'structured data',
            'faq',
            'answer engine',
            'aeo',
            'generative engine',
            'geo',
            'ai overview',
            'ai search',
            'aio',
            'llm',
        ]);
    }

    private function mentionsDiscoverabilityGap(string $text): bool
    {
        return $this->containsAny($text, [
            'no seo',
            'weak seo',
            'poor seo',
            'missing seo',
            'no metadata',
            'missing metadata',
            'weak metadata',
            'no meta description',
            'missing meta description',
            'no title tag',
            'missing title tag',
            'no schema',
            'missing schema',
            'no structured data',
            'missing structured data',
            'no faq',
            'missing faq',
            'no answer blocks',
            'missing answer blocks',
            'no aeo',
            'missing aeo',
            'no geo',
            'missing geo',
            'no aio',
            'missing aio',
            'no ai-readable',
            'not ai-readable',
            'not ranking',
            'poor search',
            'weak local search',
        ]);
    }

    /**
     * @param  array<int, array{value:mixed}>  $productServiceEvidence
     */
    private function alignmentIsEvidenced(string $websiteText, array $productServiceEvidence): bool
    {
        if ($productServiceEvidence === [] || trim($websiteText) === '') {
            return false;
        }

        $terms = $this->alignmentTerms($productServiceEvidence);

        if ($terms === []) {
            return $this->containsAny($websiteText, [
                'product',
                'products',
                'service',
                'services',
                'offer',
                'offers',
                'pricing',
                'package',
                'packages',
                'solution',
                'solutions',
            ]);
        }

        $matches = array_values(array_filter(
            $terms,
            static fn (string $term): bool => str_contains($websiteText, $term),
        ));
        $requiredMatches = min(3, max(1, (int) ceil(count($terms) * 0.25)));

        return count($matches) >= $requiredMatches;
    }

    /**
     * @param  array<int, array{value:mixed}>  $items
     * @return array<int, string>
     */
    private function alignmentTerms(array $items): array
    {
        $text = preg_replace('/[^a-z0-9]+/', ' ', $this->evidenceText($items)) ?? '';
        $words = preg_split('/\s+/', $text) ?: [];
        $stopWords = [
            'about',
            'advisory',
            'business',
            'client',
            'customer',
            'customers',
            'fixed',
            'including',
            'limited',
            'monthly',
            'offer',
            'offers',
            'package',
            'packages',
            'price',
            'pricing',
            'product',
            'products',
            'provide',
            'sells',
            'service',
            'services',
            'support',
            'they',
            'what',
        ];

        return collect($words)
            ->map(static fn (string $word): string => trim($word))
            ->filter(static fn (string $word): bool => strlen($word) >= 4 && ! in_array($word, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function answerHaystack(QuestionnaireAnswer $answer): string
    {
        return strtolower((string) $answer->question?->prompt.' '.$this->valueText($answer->value));
    }

    /**
     * @param  array<int, array{value:mixed}>  $items
     */
    private function evidenceText(array $items): string
    {
        return strtolower(implode(' ', array_map(
            fn (array $item): string => $this->valueText($item['value']),
            $items,
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
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
