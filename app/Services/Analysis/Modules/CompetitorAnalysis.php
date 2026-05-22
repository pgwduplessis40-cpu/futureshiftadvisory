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

final class CompetitorAnalysis implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.competitor';

    private const MAX_COMPETITORS = 6;

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Competitor;
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
            'competitors' => $this->competitors($client),
            'max_competitors' => self::MAX_COMPETITORS,
            'analysis_dimensions' => ['product', 'pricing', 'visibility', 'gap_analysis'],
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
        $competitors = $this->competitors($client);
        $attributions = $this->sourceAttributions($client);
        $summary = $this->competitorSummary($competitors);
        $visibilityRisk = $this->contains($competitors, ['google', 'search', 'ranking', 'visibility', 'ads']);
        $pricingRisk = $this->contains($competitors, ['price', 'pricing', 'discount', 'cheaper', 'premium']);
        $productRisk = $this->contains($competitors, ['service', 'product', 'offer', 'package', 'feature']);
        $severity = ($visibilityRisk || $pricingRisk || $productRisk) ? FindingSeverity::Medium : FindingSeverity::Low;

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Competitor set captured',
                body: sprintf(
                    'Competitor analysis includes %d competitor(s): %s.',
                    count($competitors),
                    $summary,
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $severity,
                title: 'Product, pricing, and visibility gaps',
                body: $this->diagnosticBody($productRisk, $pricingRisk, $visibilityRisk),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: $visibilityRisk ? FindingSeverity::Medium : FindingSeverity::Low,
                title: 'Competitive visibility trajectory',
                body: $visibilityRisk
                    ? 'Competitor evidence includes search, ranking, advertising, or visibility pressure, so future lead flow is exposed if the client does not sharpen channel positioning.'
                    : 'Competitor visibility pressure is not yet evidenced, so trajectory confidence depends on adding search and channel evidence before decisions are made.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $severity,
                title: 'Competitor gap action plan',
                body: 'Prioritise a side-by-side product/service comparison, pricing-position statement, and visibility plan for the highest-relevance competitors before converting gaps into advisory initiatives.',
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
            ),
        ];
    }

    /**
     * @return array<int, array{name:string, detail:string, source_reference:string}>
     */
    private function competitors(Client $client): array
    {
        $competitors = [];

        foreach ($this->competitorAnswers($client) as $answer) {
            foreach ($this->lines($answer) as $line) {
                if (count($competitors) >= self::MAX_COMPETITORS) {
                    break 2;
                }

                $competitors[] = [
                    'name' => $this->competitorName($line),
                    'detail' => $line,
                    'source_reference' => "questionnaire_answer:{$answer->id}",
                ];
            }
        }

        return $competitors;
    }

    /**
     * @return array<int, QuestionnaireAnswer>
     */
    private function competitorAnswers(Client $client): array
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
                    ->filter(fn (QuestionnaireAnswer $answer): bool => $this->isCompetitorAnswer($answer))
                    ->all();
            })
            ->values()
            ->all();
    }

    private function isCompetitorAnswer(QuestionnaireAnswer $answer): bool
    {
        $prompt = strtolower((string) $answer->question?->prompt);
        $value = strtolower((string) (is_array($answer->value) ? json_encode($answer->value) : $answer->value));
        $haystack = $prompt.' '.$value;

        foreach (['competitor', 'competition', 'pricing', 'market gap', 'visibility'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function lines(QuestionnaireAnswer $answer): array
    {
        $value = is_array($answer->value)
            ? implode("\n", array_map(static fn (mixed $item): string => (string) $item, $answer->value))
            : (string) $answer->value;

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\r\n|\r|\n/', $value) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    private function competitorName(string $line): string
    {
        $parts = preg_split('/\s[-:|]\s/', $line, 2);
        $name = trim((string) ($parts[0] ?? $line));

        return $name === '' ? 'Unnamed competitor' : $name;
    }

    /**
     * @param  array<int, array{name:string, detail:string, source_reference:string}>  $competitors
     */
    private function competitorSummary(array $competitors): string
    {
        if ($competitors === []) {
            return 'no named competitors supplied';
        }

        return implode(', ', array_map(
            static fn (array $competitor): string => $competitor['name'],
            $competitors,
        ));
    }

    /**
     * @param  array<int, array{name:string, detail:string, source_reference:string}>  $competitors
     * @param  array<int, string>  $needles
     */
    private function contains(array $competitors, array $needles): bool
    {
        $haystack = strtolower(implode(' ', array_map(
            static fn (array $competitor): string => $competitor['detail'],
            $competitors,
        )));

        foreach ($needles as $needle) {
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

        foreach ($this->competitors($client) as $competitor) {
            $attributions[] = [
                'claim' => "Competitor evidence was supplied for {$competitor['name']}.",
                'source_reference' => $competitor['source_reference'],
            ];
        }

        if ($attributions === []) {
            $attributions[] = [
                'claim' => 'Client profile identifies the competitor-analysis subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        return $attributions;
    }

    private function diagnosticBody(bool $productRisk, bool $pricingRisk, bool $visibilityRisk): string
    {
        return implode(' ', [
            $productRisk
                ? 'Product or service comparison evidence is present and should be converted into a client differentiation map.'
                : 'Product or service comparison evidence is not yet specific enough to score differentiation.',
            $pricingRisk
                ? 'Pricing evidence is present, so the client needs a clear price-position and value-proof response.'
                : 'Pricing evidence is not yet specific enough to benchmark price position.',
            $visibilityRisk
                ? 'Visibility evidence is present, so channel share and search prominence are likely competitive gaps.'
                : 'Visibility evidence is not yet specific enough to benchmark channel prominence.',
        ]);
    }
}
