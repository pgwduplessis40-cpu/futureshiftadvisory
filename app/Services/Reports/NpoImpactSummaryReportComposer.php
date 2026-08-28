<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Npo\NpoImpactMetricRecorder;
use App\Services\Reports\Contracts\NpoImpactSummaryReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\NpoImpactSummaryInput;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns client-authored Impact Summary reports and their fact-check contract.
 */
final class NpoImpactSummaryReportComposer implements NpoImpactSummaryReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
        private readonly NpoImpactMetricRecorder $impactMetrics,
    ) {}

    public function compose(NpoEngagement $engagement, NpoImpactSummaryInput $input, ?User $actor = null): Report
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Impact Summary reports require an NPO engagement with a client.');
        }

        $recordedMetrics = $this->impactMetrics->reportMetrics($engagement);
        $metrics = $input->metrics ?? $this->numericMetricMap($recordedMetrics['metrics'] ?? null);
        $platformMetrics = $input->platformMetrics ?? $this->numericMetricMap($recordedMetrics['platform_metrics'] ?? null);
        $this->assertMetricsAreFactChecked($metrics, $platformMetrics);
        $autoReleaseAt = now()->addHours((int) config('npo.impact_summary_auto_release_hours', 48));

        return DB::transaction(function () use ($client, $engagement, $input, $metrics, $platformMetrics, $autoReleaseAt, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::ImpactSummary,
                'title' => ReportType::ImpactSummary->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'client_authored' => true,
                    'fsa_ip' => false,
                    'auto_release_at' => $autoReleaseAt->toIso8601String(),
                    'platform_metrics' => $platformMetrics,
                    'redactions' => ['fsa_ip'],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'pending_review',
            ]);

            $this->persistSections($report, $client, $this->sections($engagement, $input, $metrics));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.impact_summary_report_generated',
                fn (Report $rendered): array => [
                    'npo_engagement_id' => $engagement->getKey(),
                    'client_authored' => true,
                    'auto_release_at' => (string) ($rendered->metadata['auto_release_at'] ?? ''),
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    /**
     * @param  array<string, float|int>  $metrics
     * @param  array<string, float|int>  $platformMetrics
     */
    private function assertMetricsAreFactChecked(array $metrics, array $platformMetrics): void
    {
        foreach ($metrics as $key => $value) {
            $platformValue = $platformMetrics[$key] ?? null;

            if ($platformValue !== null && $value > $platformValue) {
                throw new InvalidArgumentException("Impact metric [{$key}] exceeds recorded platform data.");
            }
        }
    }

    /**
     * @param  array<string, float|int>  $metrics
     * @return list<ReportSectionDraft>
     */
    private function sections(NpoEngagement $engagement, NpoImpactSummaryInput $input, array $metrics): array
    {
        return [
            ReportSectionDraft::generated(
                key: 'client_impact_summary',
                title: 'Client-authored impact summary',
                body: $input->summary,
                sourceReference: 'impact_summary:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: client-authored narrative; AI assistance is limited to language support and no FSA IP is included.',
                metadata: ['client_authored' => true, 'fsa_ip' => false],
            ),
            ReportSectionDraft::generated(
                key: 'fact_checked_metrics',
                title: 'Fact-checked metrics',
                body: collect($metrics)
                    ->map(fn (float|int $value, string $key): string => "{$key}: {$value}")
                    ->implode("\n") ?: 'No impact metrics supplied.',
                sourceReference: 'impact_metrics:'.$engagement->getKey(),
                metadata: ['metric_count' => count($metrics)],
            ),
        ];
    }

    /** @param list<ReportSectionDraft> $sections */
    private function persistSections(Report $report, Client $client, array $sections): void
    {
        foreach ($sections as $position => $section) {
            ReportSection::query()->create([
                ...$section->toAttributes(),
                'report_id' => $report->getKey(),
                'client_id' => $client->getKey(),
                'position' => $position + 1,
            ]);
        }
    }

    /** @param Closure(Report): array<string, bool|int|string> $after */
    private function renderAndAuditAfterCommit(Report $report, ?User $actor, string $action, Closure $after): void
    {
        $callback = function () use ($report, $actor, $action, $after): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after($report->refresh()));
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    /** @return array<string, float|int> */
    private function numericMetricMap(mixed $metrics): array
    {
        if (! is_array($metrics)) {
            return [];
        }

        $map = [];

        foreach ($metrics as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }

            $map[$key] = (float) $value;
        }

        return $map;
    }
}
