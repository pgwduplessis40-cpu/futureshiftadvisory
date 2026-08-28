<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\NpoValueCalculation;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\NpoHealthReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\NpoReportInputs;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns the NPO health and confidential advisor report types.
 */
final class NpoHealthReportComposer implements NpoHealthReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function composeHealth(NpoEngagement $engagement, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement);

        return DB::transaction(function () use ($inputs, $engagement, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::NpoHealth,
                'title' => ReportType::NpoHealth->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'plain_english' => true,
                    'board_audience' => true,
                    'redactions' => ['advisor_workings'],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections($report, $inputs->client, $this->healthSections($engagement, $inputs->scores));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.health_report_generated',
                fn (Report $rendered): array => [
                    'npo_engagement_id' => $engagement->getKey(),
                    'sections' => $rendered->sections()->count(),
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    public function composeAdvisor(NpoEngagement $engagement, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement);

        return DB::transaction(function () use ($inputs, $engagement, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::NpoAdvisor,
                'title' => ReportType::NpoAdvisor->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'confidential' => true,
                    'header_colour' => 'cognac',
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections($report, $inputs->client, $this->advisorSections($engagement, $inputs->scores));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.advisor_report_generated',
                fn (Report $rendered): array => [
                    'npo_engagement_id' => $engagement->getKey(),
                    'sections' => $rendered->sections()->count(),
                    'confidential' => true,
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    private function inputs(NpoEngagement $engagement): NpoReportInputs
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('NPO reports require an engagement with a client.');
        }

        if (! in_array($engagement->sub_type, [NpoEngagementSubType::StandardNpo, NpoEngagementSubType::SocialEnterprise], true)) {
            throw new InvalidArgumentException('NPO Health and Advisor reports require a full NPO engagement.');
        }

        $batchId = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->select('assessment_batch_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('assessment_batch_id')
            ->value('assessment_batch_id');

        if (! is_string($batchId)) {
            throw new InvalidArgumentException('NPO reports require a recorded NPO health assessment.');
        }

        /** @var Collection<int, NpoDimensionScore> $scores */
        $scores = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('assessment_batch_id', $batchId)
            ->orderBy('dimension_number')
            ->get();

        if ($scores->isEmpty()) {
            throw new InvalidArgumentException('NPO reports require a recorded NPO health assessment.');
        }

        return new NpoReportInputs($client, $scores);
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     * @return list<ReportSectionDraft>
     */
    private function healthSections(NpoEngagement $engagement, Collection $scores): array
    {
        $healthScore = (int) ($scores->first()?->health_score ?? round((float) $scores->avg('score')));
        $strongest = $scores->sortByDesc('score')->first();
        $priority = $scores->sortBy('score')->first();

        return [
            ReportSectionDraft::generated(
                key: 'health_snapshot',
                title: 'Health snapshot',
                body: sprintf(
                    'Current NPO health score: %s/100. Strongest area: %s. Priority area: %s. The score is about mission delivery strength, not commercial return.',
                    $healthScore,
                    $strongest?->dimension_label ?? 'not recorded',
                    $priority?->dimension_label ?? 'not recorded',
                ),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: plain-English client summary from the latest NPO health assessment.',
                metadata: ['health_score' => $healthScore],
            ),
            ReportSectionDraft::generated(
                key: 'dimension_scores',
                title: 'Dimension scores',
                body: $scores
                    ->map(fn (NpoDimensionScore $score): string => "{$score->dimension_label}: {$score->score}/100")
                    ->implode("\n"),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: each dimension score is persisted with source attributions and advisor weighting.',
                metadata: ['dimension_score_ids' => $scores->pluck('id')->values()->all()],
            ),
            ReportSectionDraft::generated(
                key: 'priority_findings',
                title: 'Priority findings',
                body: $this->findingLines($scores),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey().':findings',
                dataQualityNote: 'Data quality note: findings are copied from the scored assessment and should stay tied to their cited evidence.',
            ),
        ];
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     * @return list<ReportSectionDraft>
     */
    private function advisorSections(NpoEngagement $engagement, Collection $scores): array
    {
        $healthScore = (int) ($scores->first()?->health_score ?? round((float) $scores->avg('score')));
        /** @var Collection<int, NpoValueCalculation> $calculations */
        $calculations = NpoValueCalculation::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByDesc('calculated_at')
            ->get()
            ->unique('type')
            ->values();

        return [
            ReportSectionDraft::generated(
                key: 'confidential_header',
                title: 'CONFIDENTIAL - NPO Advisor Report',
                body: 'CONFIDENTIAL advisor working paper. Header colour: Cognac. This report contains full workings and should not be released as the client-facing NPO Health Report.',
                sourceReference: 'npo_advisor_report:'.$engagement->getKey().':confidential',
                dataQualityNote: 'Data quality note: advisor-only report; release only through advisor-controlled channels.',
                metadata: ['confidential' => true, 'header_colour' => 'cognac'],
            ),
            ReportSectionDraft::generated(
                key: 'full_health_workings',
                title: 'Full NPO health workings',
                body: "Aggregate health score: {$healthScore}/100.\n\n".$scores
                    ->map(fn (NpoDimensionScore $score): string => "{$score->dimension_number}. {$score->dimension_label}: score {$score->score}, weight {$score->advisor_weight}, weighted {$score->weighted_score}.")
                    ->implode("\n"),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: advisor workings include weights and weighted scores from persisted assessment records.',
            ),
            ReportSectionDraft::generated(
                key: 'mission_roi_value_workings',
                title: 'Mission ROI value workings',
                body: $calculations->isEmpty()
                    ? 'No NPO value calculations have been recorded yet.'
                    : $calculations
                        ->map(fn (NpoValueCalculation $calculation): string => "{$calculation->type}: {$this->money($calculation->projection_mid)} midpoint, range {$this->money($calculation->projection_low)} to {$this->money($calculation->projection_high)}. Verification: ".(string) data_get($calculation->result, 'impact_governance.verification_label', 'Internal estimate - not externally verified').'. Theory of change: '.(string) data_get($calculation->result, 'impact_governance.theory_of_change_status', 'not_captured').'. Stakeholders: '.(string) data_get($calculation->result, 'impact_governance.stakeholder_involvement_status', 'not_captured').'. Mission framing: '.(string) ($calculation->result['mission_framing'] ?? 'Mission impact framing retained.'))
                        ->implode("\n"),
                sourceReference: 'npo_value_calculations:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: values include the mandatory +/-15% uncertainty range and are framed as mission ROI, not commercial profit.',
                metadata: ['npo_value_calculation_ids' => $calculations->pluck('id')->values()->all()],
            ),
            ReportSectionDraft::generated(
                key: 'advisor_recommendation_frame',
                title: 'Advisor recommendation frame',
                body: $this->findingLines($scores),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey().':findings',
                dataQualityNote: 'Data quality note: recommendation framing must preserve source evidence and avoid inflating mission outcomes.',
            ),
        ];
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     */
    private function findingLines(Collection $scores): string
    {
        $lines = $scores
            ->flatMap(fn (NpoDimensionScore $score): array => collect($score->findings ?? [])
                ->map(fn (mixed $finding): string => is_array($finding)
                    ? ($score->dimension_label.': '.(string) ($finding['title'] ?? 'Finding').' - '.(string) ($finding['body'] ?? ''))
                    : ($score->dimension_label.': '.(string) $finding))
                ->all())
            ->values();

        return $lines->isEmpty()
            ? 'No priority findings were recorded in the latest NPO health assessment.'
            : $lines->implode("\n");
    }

    /**
     * @param  list<ReportSectionDraft>  $sections
     */
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

    /**
     * @param  Closure(Report): array<string, bool|int|string|null>  $after
     */
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

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }
}
