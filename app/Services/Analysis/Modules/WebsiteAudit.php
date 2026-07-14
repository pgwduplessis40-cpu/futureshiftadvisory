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
use App\Models\WebsiteAuditSnapshot;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Analysis\WebsiteAuditSnapshotContext;
use App\Services\DataQuality\DataQualityScore;
use Illuminate\Database\Eloquent\Collection;

final class WebsiteAudit implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.website_audit';

    public function __construct(private readonly WebsiteAuditSnapshotContext $context) {}

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
        $snapshot = $this->snapshot($client);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
            ],
            'stated_offer_evidence' => $this->productServiceEvidence($client),
            'website_snapshot' => [
                'id' => $snapshot?->getKey(),
                'root_url' => $snapshot?->root_url,
                'fetched_at' => $snapshot?->fetched_at?->toIso8601String(),
                'pages' => data_get($snapshot?->ai_evidence, 'pages', []),
                'deterministic_signals' => [
                    'scores' => $snapshot?->scores,
                    'technical' => $snapshot?->technical,
                    'performance' => $snapshot?->performance,
                    'nz_compliance' => $snapshot?->nz_compliance,
                ],
            ],
            'audit_scope' => [
                'product_service_content_alignment',
                'value_proposition_clarity',
                'trust_signals',
                'seo_geo_aeo_aio_extractability',
                'conversion_calls_to_action',
            ],
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        $snapshot = $this->snapshot($client);
        $website = collect((array) ($snapshot?->source_attributions ?? []))
            ->pluck('source_reference')
            ->filter()
            ->map(fn (mixed $reference): string => (string) $reference)
            ->all();

        return array_values(array_unique([...$website, ...array_column($this->offerAttributions($client), 'source_reference')]));
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        $snapshot = $this->snapshot($client);
        if (! $snapshot instanceof WebsiteAuditSnapshot) {
            return [];
        }

        $pages = (array) ($snapshot->pages ?? []);
        $attributions = (array) ($snapshot->source_attributions ?? []);
        if ($pages === [] || $attributions === []) {
            return [];
        }

        $scores = (array) ($snapshot->scores ?? []);
        $technical = (array) ($snapshot->technical ?? []);
        $performance = (array) ($snapshot->performance ?? []);
        $compliance = (array) ($snapshot->nz_compliance ?? []);
        $issues = $this->issues($scores, $technical, $performance, $compliance, $pages);
        $severity = $this->severity($scores, $technical);
        $websiteAttributions = $this->websiteAttributions($attributions);
        $alignmentAttributions = [...$websiteAttributions, ...$this->offerAttributions($client)];

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Verified website audit snapshot',
                body: sprintf(
                    'The audit fetched and parsed %d page(s) from %s at %s. Health dimensions: findability %s, credibility %s, conversion %s, technical %s.',
                    count($pages),
                    (string) $snapshot->root_url,
                    $snapshot->fetched_at?->toIso8601String() ?? 'not recorded',
                    $this->scoreLabel($scores['findability'] ?? null),
                    $this->scoreLabel($scores['credibility'] ?? null),
                    $this->scoreLabel($scores['conversion'] ?? null),
                    $this->scoreLabel($scores['technical'] ?? null),
                ),
                attributions: $websiteAttributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Low,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Verified findability and technical gaps',
                body: $issues === []
                    ? 'No material deterministic findability or technical gap was detected in the fetched pages. Continue monitoring as the site changes.'
                    : 'Measured website gaps: '.implode('; ', $issues).'.',
                attributions: $websiteAttributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Low,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $severity,
                title: 'Website discoverability and conversion outlook',
                body: 'Examiner assessment of the fetched page text against the stated offer: '.trim($response->text).' Score source: deterministic website signals plus examiner review; PageSpeed measurements are '.(($performance['measured'] ?? false) ? 'available' : 'not measured').'.',
                attributions: $alignmentAttributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Verified website improvement priorities',
                body: $this->actionPlan($issues, $scores),
                attributions: $websiteAttributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    private function snapshot(Client $client): ?WebsiteAuditSnapshot
    {
        return $this->context->forClient($client);
    }

    /**
     * @return array<int, array{answer_id:string,prompt:string|null,value:mixed}>
     */
    private function productServiceEvidence(Client $client): array
    {
        return $this->responses($client)
            ->flatMap(fn (QuestionnaireResponse $response) => $response->answers)
            ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isOfferAnswer($answer))
            ->map(fn (QuestionnaireAnswer $answer): array => [
                'answer_id' => (string) $answer->getKey(),
                'prompt' => $answer->question?->prompt,
                'value' => $answer->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{claim:string,source_reference:string}>
     */
    private function offerAttributions(Client $client): array
    {
        return array_map(static fn (array $item): array => [
            'claim' => 'The client stated this product or service evidence in the Standard Advisory questionnaire.',
            'source_reference' => 'questionnaire_answer:'.$item['answer_id'],
        ], $this->productServiceEvidence($client));
    }

    /**
     * @return Collection<int, QuestionnaireResponse>
     */
    private function responses(Client $client): Collection
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

    private function isOfferAnswer(QuestionnaireAnswer $answer): bool
    {
        $value = is_array($answer->value) ? json_encode($answer->value) : (string) $answer->value;
        $haystack = strtolower((string) $answer->question?->prompt.' '.$value);

        return preg_match('/\b(product|service|offer|package|pricing|customer|market|sell|solution)\b/', $haystack) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attributions
     * @return array<int, array{claim:string,source_reference:string}>
     */
    private function websiteAttributions(array $attributions): array
    {
        return array_values(array_filter($attributions, static fn (array $attribution): bool => trim((string) ($attribution['source_reference'] ?? '')) !== ''));
    }

    /**
     * @param  array<string, mixed>  $scores
     * @param  array<string, mixed>  $technical
     */
    private function severity(array $scores, array $technical): FindingSeverity
    {
        if ((int) ($technical['error_page_count'] ?? 0) > 0 || min(array_filter([
            $scores['findability'] ?? null,
            $scores['credibility'] ?? null,
            $scores['conversion'] ?? null,
            $scores['technical'] ?? null,
        ], 'is_numeric') ?: [100]) < 45) {
            return FindingSeverity::High;
        }

        return min(array_filter([
            $scores['findability'] ?? null,
            $scores['credibility'] ?? null,
            $scores['conversion'] ?? null,
            $scores['technical'] ?? null,
        ], 'is_numeric') ?: [100]) < 70 ? FindingSeverity::Medium : FindingSeverity::Low;
    }

    /**
     * @param  array<string, mixed>  $scores
     * @param  array<string, mixed>  $technical
     * @param  array<string, mixed>  $performance
     * @param  array<string, mixed>  $compliance
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, string>
     */
    private function issues(array $scores, array $technical, array $performance, array $compliance, array $pages): array
    {
        $issues = [];
        if ((int) ($scores['findability'] ?? 100) < 70) {
            $issues[] = 'findability score is '.$this->scoreLabel($scores['findability'] ?? null);
        }
        if ((int) ($scores['conversion'] ?? 100) < 70) {
            $issues[] = 'conversion score is '.$this->scoreLabel($scores['conversion'] ?? null);
        }
        if ((int) ($technical['error_page_count'] ?? 0) > 0) {
            $issues[] = (int) $technical['error_page_count'].' fetched page(s) returned HTTP 4xx or 5xx';
        }
        if (($compliance['privacy_policy_present'] ?? false) === false) {
            $issues[] = 'no privacy-policy presence signal was found in the fetched pages';
        }
        if (($performance['measured'] ?? false) && is_numeric($performance['lcp_ms'] ?? null) && (float) $performance['lcp_ms'] > 2500) {
            $issues[] = 'mobile LCP is '.round((float) $performance['lcp_ms'] / 1000, 1).'s against a 2.5s reference threshold';
        }
        if (collect($pages)->contains(fn (array $page): bool => (array) ($page['schema_types'] ?? []) === [])) {
            $issues[] = 'at least one fetched page has no JSON-LD structured-data signal';
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<string, mixed>  $scores
     */
    private function actionPlan(array $issues, array $scores): string
    {
        if ($issues === []) {
            return 'Keep the current website controls under review and re-audit after material content, platform, or conversion-path changes.';
        }

        return 'Prioritise the measured gaps: '.implode('; ', $issues).'. Re-audit after changes to establish a before-and-after health score (current overall '.$this->scoreLabel($scores['overall'] ?? null).').';
    }

    private function scoreLabel(mixed $value): string
    {
        return is_numeric($value) ? (string) ((int) $value).'/100' : 'not measured';
    }
}
