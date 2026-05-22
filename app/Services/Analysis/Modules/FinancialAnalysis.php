<?php

declare(strict_types=1);

namespace App\Services\Analysis\Modules;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\EconomicIndicator;
use App\Models\FinancialSnapshot;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\DataQuality\DataQualityScore;
use Illuminate\Support\Collection;

final class FinancialAnalysis implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.financial';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Financial;
    }

    public function promptId(): string
    {
        return self::PROMPT_ID;
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        $snapshot = $this->latestSnapshot($client);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'engagement_type' => $client->engagement_type?->value ?? $client->engagement_type,
            ],
            'basis' => $snapshot instanceof FinancialSnapshot ? 'financial_snapshot' : 'questionnaire_fallback',
            'latest_snapshot' => $snapshot instanceof FinancialSnapshot ? $this->snapshotPayload($snapshot) : null,
            'questionnaire_context' => $snapshot instanceof FinancialSnapshot ? [] : $this->questionnaireContext($client),
            'economic_overlay' => $this->economicOverlay()
                ->map(fn (EconomicIndicator $indicator): array => $this->indicatorPayload($indicator))
                ->values()
                ->all(),
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
        $snapshot = $this->latestSnapshot($client);
        $attributions = $this->sourceAttributions($client);
        $economic = $this->economicOverlay();
        $disclaimer = $snapshot instanceof FinancialSnapshot ? null : $this->accountingFallbackDisclaimer();

        if (! $snapshot instanceof FinancialSnapshot) {
            return $this->fallbackFindings($client, $attributions, $disclaimer);
        }

        $metrics = $this->metrics($snapshot);
        $drivers = $this->drivers($metrics);
        $riskSeverity = $this->riskSeverity($metrics);

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Financial baseline from connected accounts',
                body: sprintf(
                    'For the period ending %s, revenue is %s, gross margin is %s, net margin is %s, operating cash flow is %s, and current ratio is %s.',
                    $snapshot->period_end?->toDateString() ?? 'unknown',
                    $this->money($metrics['revenue']),
                    $this->percent($metrics['gross_margin']),
                    $this->percent($metrics['net_margin']),
                    $this->money($metrics['operating_cash_flow']),
                    $this->ratio($metrics['current_ratio']),
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
                dataQualityDisclaimer: $disclaimer,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: $riskSeverity,
                title: 'Financial performance drivers',
                body: 'Primary financial drivers: '.implode(' ', $drivers),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
                dataQualityDisclaimer: $disclaimer,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: FindingSeverity::Medium,
                title: 'Economic overlay on financial trajectory',
                body: $this->economicBody($economic, $metrics),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: $response->uncertainty,
                dataQualityDisclaimer: $disclaimer,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: $riskSeverity,
                title: 'Financial improvement opportunity',
                body: sprintf(
                    'A focused margin and cash-conversion programme should target at least %s annual benefit, based on 2%% of latest revenue, then validate the value through WO-42 improvement PV.',
                    $this->money($this->estimatedAnnualBenefit($snapshot)),
                ),
                attributions: $attributions,
                documentSupport: AnalysisFinding::DOCUMENT_SUPPORT_NONE,
                uncertainty: Uncertainty::Medium,
                dataQualityDisclaimer: $disclaimer,
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function improvementOpportunity(Client $client): ?array
    {
        $snapshot = $this->latestSnapshot($client);

        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        return [
            'title' => 'Financial margin and cash-conversion uplift',
            'annual_benefit' => $this->estimatedAnnualBenefit($snapshot),
            'duration_years' => 3,
            'source_reference' => "financial_snapshot:{$snapshot->id}:profit_and_loss.revenue",
        ];
    }

    private function latestSnapshot(Client $client): ?FinancialSnapshot
    {
        return FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('period_end')
            ->latest('pulled_at')
            ->latest()
            ->first();
    }

    /**
     * @return Collection<int, EconomicIndicator>
     */
    private function economicOverlay(): Collection
    {
        return EconomicIndicator::query()
            ->whereIn('indicator', [
                EconomicIndicator::OCR,
                EconomicIndicator::CPI_ANNUAL,
                EconomicIndicator::GDP_QUARTERLY,
                EconomicIndicator::UNEMPLOYMENT_RATE,
            ])
            ->latest('period_date')
            ->latest('fetched_at')
            ->get()
            ->unique('indicator')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(FinancialSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'provider' => $snapshot->provider,
            'period_start' => $snapshot->period_start?->toDateString(),
            'period_end' => $snapshot->period_end?->toDateString(),
            'source_badge' => $snapshot->source_badge,
            'degraded' => $snapshot->degraded,
            'profit_and_loss' => $snapshot->profit_and_loss,
            'balance_sheet' => $snapshot->balance_sheet,
            'cash_flow' => $snapshot->cash_flow,
            'metrics' => $snapshot->metrics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function indicatorPayload(EconomicIndicator $indicator): array
    {
        return [
            'id' => $indicator->id,
            'indicator' => $indicator->indicator,
            'label' => $indicator->label,
            'value' => $indicator->value,
            'unit' => $indicator->unit,
            'period_date' => $indicator->period_date?->toDateString(),
            'source' => $indicator->source,
            'source_badge' => $indicator->source_badge,
            'degraded' => $indicator->degraded,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionnaireContext(Client $client): array
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(2)
            ->get()
            ->flatMap(function (QuestionnaireResponse $response): array {
                return $response->answers
                    ->map(function (QuestionnaireAnswer $answer) use ($response): array {
                        return [
                            'response_id' => $response->id,
                            'answer_id' => $answer->id,
                            'prompt' => $answer->question?->prompt,
                            'value' => $answer->value,
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function metrics(FinancialSnapshot $snapshot): array
    {
        $profit = $snapshot->profit_and_loss ?? [];
        $cashFlow = $snapshot->cash_flow ?? [];
        $metrics = $snapshot->metrics ?? [];

        $revenue = $this->number(data_get($profit, 'revenue'));
        $grossProfit = $this->number(data_get($profit, 'gross_profit'));
        $operatingExpenses = $this->number(data_get($profit, 'operating_expenses'));
        $netProfit = $this->number(data_get($profit, 'net_profit'));

        return [
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
            'operating_cash_flow' => $this->number(data_get($cashFlow, 'operating_cash_flow')),
            'gross_margin' => $this->number(data_get($metrics, 'gross_margin'), $this->safeRatio($grossProfit, $revenue)),
            'net_margin' => $this->number(data_get($metrics, 'net_margin'), $this->safeRatio($netProfit, $revenue)),
            'current_ratio' => $this->number(data_get($metrics, 'current_ratio')),
        ];
    }

    /**
     * @param  array<string, float>  $metrics
     * @return array<int, string>
     */
    private function drivers(array $metrics): array
    {
        $drivers = [];

        $drivers[] = sprintf('Gross profit contributes %s on revenue of %s.', $this->money($metrics['gross_profit']), $this->money($metrics['revenue']));
        $drivers[] = sprintf('Operating expenses consume %s of revenue.', $this->percent($this->safeRatio($metrics['operating_expenses'], $metrics['revenue'])));

        if ($metrics['net_margin'] < 0.08) {
            $drivers[] = 'Net margin is thin, so small cost or pricing movements can materially change profit.';
        } else {
            $drivers[] = 'Net margin is above the thin-margin trigger, so growth quality and working capital become the next driver to watch.';
        }

        if ($metrics['operating_cash_flow'] < $metrics['net_profit']) {
            $drivers[] = 'Operating cash flow is below net profit, which points to working-capital or collection timing pressure.';
        } else {
            $drivers[] = 'Operating cash flow is supporting reported profit.';
        }

        if ($metrics['current_ratio'] > 0 && $metrics['current_ratio'] < 1.2) {
            $drivers[] = 'Current ratio is below 1.20, indicating liquidity pressure.';
        }

        return $drivers;
    }

    /**
     * @param  Collection<int, EconomicIndicator>  $economic
     * @param  array<string, float>  $metrics
     */
    private function economicBody(Collection $economic, array $metrics): string
    {
        $indicators = $economic
            ->map(fn (EconomicIndicator $indicator): string => sprintf(
                '%s %s%s',
                $indicator->label,
                number_format($indicator->value, 2),
                $indicator->unit === 'percent' ? '%' : ' '.$indicator->unit,
            ))
            ->values()
            ->all();

        $overlay = $indicators === []
            ? 'No current NZ economic indicators are available, so the trajectory relies on internal financial evidence.'
            : 'NZ economic overlay: '.implode('; ', $indicators).'.';

        return sprintf(
            '%s With net margin at %s and current ratio at %s, pricing power, wage inflation, and debt-servicing sensitivity should be reviewed before relying on the current trajectory.',
            $overlay,
            $this->percent($metrics['net_margin']),
            $this->ratio($metrics['current_ratio']),
        );
    }

    /**
     * @return array<int, AnalysisFindingData>
     */
    private function fallbackFindings(Client $client, array $attributions, string $disclaimer): array
    {
        $contextCount = count($this->questionnaireContext($client));

        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Descriptive,
                severity: FindingSeverity::Info,
                title: 'Financial analysis uses questionnaire fallback',
                body: "No connected accounting snapshot is available. The module found {$contextCount} questionnaire answer(s) to use as financial context.",
                attributions: $attributions,
                dataQualityDisclaimer: $disclaimer,
                uncertainty: Uncertainty::High,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::Medium,
                title: 'Financial drivers need accounting validation',
                body: 'Profitability, cash-flow drivers, and liquidity ratios cannot be quantified until an accounting snapshot is connected or manually supplied.',
                attributions: $attributions,
                dataQualityDisclaimer: $disclaimer,
                uncertainty: Uncertainty::High,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Predictive,
                severity: FindingSeverity::Medium,
                title: 'Trajectory confidence is constrained',
                body: 'Without connected accounts, the economic overlay can flag external sensitivity but cannot project financial trajectory from verified ratios.',
                attributions: $attributions,
                dataQualityDisclaimer: $disclaimer,
                uncertainty: Uncertainty::High,
            ),
            new AnalysisFindingData(
                lens: AnalysisLens::Prescriptive,
                severity: FindingSeverity::Medium,
                title: 'Connect accounting before PV-backed financial actions',
                body: 'Connect Xero, MYOB, or QuickBooks before turning financial recommendations into PV-ranked improvement opportunities.',
                attributions: $attributions,
                dataQualityDisclaimer: $disclaimer,
                uncertainty: Uncertainty::High,
            ),
        ];
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(Client $client): array
    {
        $snapshot = $this->latestSnapshot($client);
        $attributions = [];

        if ($snapshot instanceof FinancialSnapshot) {
            foreach ([
                'profit_and_loss.revenue' => 'Revenue comes from the connected accounting snapshot.',
                'profit_and_loss.gross_profit' => 'Gross profit comes from the connected accounting snapshot.',
                'profit_and_loss.operating_expenses' => 'Operating expenses come from the connected accounting snapshot.',
                'profit_and_loss.net_profit' => 'Net profit comes from the connected accounting snapshot.',
                'cash_flow.operating_cash_flow' => 'Operating cash flow comes from the connected accounting snapshot.',
                'metrics.gross_margin' => 'Gross margin comes from the connected accounting snapshot.',
                'metrics.net_margin' => 'Net margin comes from the connected accounting snapshot.',
                'metrics.current_ratio' => 'Current ratio comes from the connected accounting snapshot.',
            ] as $path => $claim) {
                $attributions[] = [
                    'claim' => $claim,
                    'source_reference' => "financial_snapshot:{$snapshot->id}:{$path}",
                ];
            }
        } else {
            $responses = QuestionnaireResponse::query()
                ->where('client_id', $client->getKey())
                ->latest('submitted_at')
                ->latest()
                ->limit(2)
                ->get();

            foreach ($responses as $response) {
                $attributions[] = [
                    'claim' => 'Questionnaire response is used because no connected accounting snapshot is available.',
                    'source_reference' => "questionnaire_response:{$response->id}",
                ];
            }

            $attributions[] = [
                'claim' => 'Client profile identifies the analysis subject.',
                'source_reference' => "client:{$client->id}",
            ];
        }

        foreach ($this->economicOverlay() as $indicator) {
            $attributions[] = [
                'claim' => "{$indicator->label} is included in the NZ economic overlay.",
                'source_reference' => "economic_indicator:{$indicator->id}:{$indicator->indicator}",
            ];
        }

        return $attributions;
    }

    private function estimatedAnnualBenefit(FinancialSnapshot $snapshot): float
    {
        $metrics = $this->metrics($snapshot);

        return round(max(5000.0, $metrics['revenue'] * 0.02), 2);
    }

    private function riskSeverity(array $metrics): FindingSeverity
    {
        if ($metrics['operating_cash_flow'] < 0 || ($metrics['current_ratio'] > 0 && $metrics['current_ratio'] < 1.0)) {
            return FindingSeverity::High;
        }

        if ($metrics['net_margin'] < 0.08 || ($metrics['current_ratio'] > 0 && $metrics['current_ratio'] < 1.2)) {
            return FindingSeverity::Medium;
        }

        return FindingSeverity::Low;
    }

    private function accountingFallbackDisclaimer(): string
    {
        return 'No connected accounting snapshot is available; this financial analysis uses questionnaire inputs and should be treated as lower confidence until Xero, MYOB, or QuickBooks data is connected.';
    }

    private function number(mixed $value, float $fallback = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function safeRatio(float $part, float $total): float
    {
        return $total === 0.0 ? 0.0 : $part / $total;
    }

    private function money(float $value): string
    {
        return 'NZD '.number_format($value, 0);
    }

    private function percent(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }

    private function ratio(float $value): string
    {
        return number_format($value, 2);
    }
}
