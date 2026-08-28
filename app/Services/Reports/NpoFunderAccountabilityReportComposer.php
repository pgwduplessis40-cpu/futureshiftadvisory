<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\FinancialSnapshot;
use App\Models\Milestone;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Services\Npo\NpoImpactMetricRecorder;
use App\Services\Reports\Contracts\NpoFunderAccountabilityReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\NpoFunderAccountabilityInputs;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the advisor-reviewed Funder Accountability report type.
 *
 * @phpstan-type Milestones EloquentCollection<int, Milestone>
 */
final class NpoFunderAccountabilityReportComposer implements NpoFunderAccountabilityReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
        private readonly NpoImpactMetricRecorder $impactMetrics,
        private readonly AiClient $ai,
    ) {}

    public function compose(NpoEngagement $engagement, ?ClientFunderRecord $record = null, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement, $record);

        return DB::transaction(function () use ($engagement, $inputs, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::FunderAccountability,
                'title' => ReportType::FunderAccountability->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'client_funder_record_id' => $inputs->record->getKey(),
                    'funder_id' => $inputs->record->funder_id,
                    'advisor_review_required' => true,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'pending_review',
            ]);

            $this->persistSections($report, $inputs->client, $this->sections($engagement, $inputs->record, $actor));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.funder_accountability_report_generated',
                fn (): array => [
                    'npo_engagement_id' => $engagement->getKey(),
                    'client_funder_record_id' => $inputs->record->getKey(),
                    'review_status' => 'pending_review',
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    private function inputs(NpoEngagement $engagement, ?ClientFunderRecord $record): NpoFunderAccountabilityInputs
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Funder Accountability reports require an NPO engagement with a client.');
        }

        $record ??= ClientFunderRecord::query()
            ->with('funder')
            ->where('client_id', $client->getKey())
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('period_end')
            ->first();

        if (! $record instanceof ClientFunderRecord) {
            throw new InvalidArgumentException('Funder Accountability reports require an engagement-scoped funder record.');
        }

        if ((string) $record->npo_engagement_id !== (string) $engagement->getKey() || (string) $record->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('Funder record must belong to the report engagement.');
        }

        $record->loadMissing('funder');

        return new NpoFunderAccountabilityInputs($client, $record);
    }

    /** @return list<ReportSectionDraft> */
    private function sections(NpoEngagement $engagement, ClientFunderRecord $record, ?User $actor): array
    {
        $snapshot = FinancialSnapshot::query()
            ->where('client_id', $engagement->client_id)
            ->latest('period_end')
            ->first();
        /** @var Milestones $milestones */
        $milestones = Milestone::query()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderBy('due_date')
            ->get();
        $impactMetrics = $this->impactMetrics->reportMetrics($engagement);
        $metricPayload = $impactMetrics['payload'] ?? null;
        $platformMetrics = $impactMetrics['platform_metrics'] ?? null;
        $completed = $milestones->where('status', Milestone::STATUS_COMPLETED)->count();
        $total = $milestones->count();
        $response = $this->ai->summarise(new PromptEnvelope(
            id: 'npo.funder_accountability_narrative',
            version: '1.0',
            task: 'Draft an advisor-review-required funder accountability narrative from persisted grant, financial, milestone, and impact data.',
            body: 'Use only supplied facts. Return a concise narrative for advisor review before funder release.',
            input: [
                'grant' => [
                    'funder_name' => $record->funder?->name,
                    'grant_name' => $record->grant_name,
                    'grant_amount' => $record->grant_amount,
                    'period_end' => $record->period_end?->toDateString(),
                ],
                'milestones' => [
                    'completed' => $completed,
                    'total' => $total,
                ],
                'impact_metrics' => $metricPayload,
                'financial_snapshot_id' => $snapshot?->getKey(),
            ],
            sourceReferences: array_values(array_filter([
                'client_funder_record:'.$record->getKey(),
                $snapshot instanceof FinancialSnapshot ? 'financial_snapshot:'.$snapshot->getKey() : null,
                'milestones:'.$engagement->getKey(),
            ])),
        ));

        return [
            ReportSectionDraft::generated(
                key: 'financial_acquittal',
                title: 'Financial acquittal',
                body: $this->financialAcquittalBody($snapshot),
                sourceReference: $snapshot instanceof FinancialSnapshot ? 'financial_snapshot:'.$snapshot->getKey() : 'financial_snapshot:none:'.$engagement->getKey(),
            ),
            ReportSectionDraft::generated(
                key: 'milestone_completion',
                title: 'Milestone completion',
                body: $total === 0
                    ? 'No engagement-scoped milestones have been recorded for this funder report.'
                    : "{$completed} of {$total} engagement-scoped milestones are complete.",
                sourceReference: 'milestones:'.$engagement->getKey(),
                metadata: ['milestone_ids' => $this->milestoneIds($milestones)],
            ),
            ReportSectionDraft::generated(
                key: 'impact_metrics',
                title: 'Impact metrics',
                body: $this->impactMetricLines($metricPayload)
                    ?: 'No client-entered impact metrics have been recorded for this engagement yet.',
                sourceReference: 'impact_metrics:'.$engagement->getKey(),
                metadata: [
                    'metric_count' => $this->metricCount($metricPayload),
                    'platform_metric_count' => $this->metricCount($platformMetrics),
                ],
            ),
            ReportSectionDraft::generated(
                key: 'ai_accountability_narrative',
                title: 'Advisor-reviewed accountability narrative',
                body: $response->text,
                sourceReference: 'ai_response:'.$response->promptHash,
                dataQualityNote: 'Data quality note: FSA-generated AI narrative is blocked from funder/client release until advisor review marks the report reviewed.',
                metadata: [
                    'advisor_review_required' => true,
                    'ai_response_hash' => $response->promptHash,
                    'generated_by_user_id' => $actor instanceof User ? (string) $actor->getKey() : '',
                ],
            ),
        ];
    }

    private function financialAcquittalBody(?FinancialSnapshot $snapshot): string
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            return 'Connected accounting data is not available yet; advisor review is required before funder release.';
        }

        return sprintf(
            'Latest accounting snapshot for %s reports revenue of NZD %s and operating expenses of NZD %s.',
            $snapshot->period_end->toDateString(),
            number_format($this->numericMapValue($snapshot->profit_and_loss, 'revenue'), 0),
            number_format($this->numericMapValue($snapshot->profit_and_loss, 'operating_expenses'), 0),
        );
    }

    /** @param Milestones $milestones
     * @return list<string>
     */
    private function milestoneIds(EloquentCollection $milestones): array
    {
        return $milestones
            ->map(fn (Milestone $milestone): string => (string) $milestone->getKey())
            ->values()
            ->all();
    }

    private function impactMetricLines(mixed $metrics): string
    {
        if (! is_array($metrics)) {
            return '';
        }

        $lines = [];

        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $value = $this->displayValue($metric['value'] ?? null);
            $unit = $this->scalarString($metric['unit'] ?? null);
            $label = $this->scalarString($metric['metric_label'] ?? $metric['metric_key'] ?? null) ?: 'Metric';
            $platform = $this->displayValue($metric['platform_value'] ?? null);
            $suffix = $unit === '' ? '' : " {$unit}";
            $platformNote = $platform === '' ? '' : " (platform cap {$platform}{$suffix})";

            $lines[] = "{$label}: {$value}{$suffix}{$platformNote}";
        }

        return implode("\n", $lines);
    }

    private function metricCount(mixed $metrics): int
    {
        return is_array($metrics) ? count($metrics) : 0;
    }

    private function numericMapValue(mixed $values, string $key): float
    {
        if (! is_array($values) || ! isset($values[$key]) || ! is_numeric($values[$key])) {
            return 0.0;
        }

        return (float) $values[$key];
    }

    private function displayValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
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

    /** @param Closure(): array<string, bool|int|string> $after */
    private function renderAndAuditAfterCommit(Report $report, ?User $actor, string $action, Closure $after): void
    {
        $callback = function () use ($report, $actor, $action, $after): void {
            $report->refresh()->load(['client', 'sections']);
            $this->artifacts->render($report);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after());
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
