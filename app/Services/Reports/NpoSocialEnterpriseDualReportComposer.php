<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\NpoSocialEnterpriseDualReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Data\NpoSocialEnterpriseDualInputs;
use App\Services\Reports\Data\ReportSectionDraft;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns Social Enterprise Dual Impact reports and their scorecard/evidence contract.
 */
final class NpoSocialEnterpriseDualReportComposer implements NpoSocialEnterpriseDualReportComposition
{
    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
    ) {}

    public function compose(NpoEngagement $engagement, ?User $actor = null): Report
    {
        $inputs = $this->inputs($engagement);

        return DB::transaction(function () use ($engagement, $inputs, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $inputs->client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::SocialEnterpriseDual,
                'title' => ReportType::SocialEnterpriseDual->label().' - '.$inputs->client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'scorecard_id' => $inputs->scorecard->getKey(),
                    'tension_analysis_id' => $inputs->analysis->getKey(),
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            $this->persistSections($report, $inputs->client, $this->sections($inputs->scorecard, $inputs->analysis));
            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'npo.social_enterprise_dual_impact_report_generated',
                fn (): array => [
                    'npo_engagement_id' => $engagement->getKey(),
                    'scorecard_id' => $inputs->scorecard->getKey(),
                    'tension_analysis_id' => $inputs->analysis->getKey(),
                ],
            );

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    private function inputs(NpoEngagement $engagement): NpoSocialEnterpriseDualInputs
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Social Enterprise Dual Impact reports require an NPO engagement with a client.');
        }

        if ($engagement->sub_type !== NpoEngagementSubType::SocialEnterprise || ! $engagement->social_enterprise) {
            throw new InvalidArgumentException('Social Enterprise Dual Impact reports require a social-enterprise engagement.');
        }

        $scorecard = NpoSocialEnterpriseScorecard::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('calculated_at')
            ->first();
        $analysis = NpoTensionAnalysis::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('review_status', NpoTensionAnalysis::REVIEW_REVIEWED)
            ->latest('reviewed_at')
            ->latest('generated_at')
            ->first();

        if (! $scorecard instanceof NpoSocialEnterpriseScorecard || ! $analysis instanceof NpoTensionAnalysis) {
            throw new InvalidArgumentException('Social Enterprise Dual Impact reports require a scorecard and advisor-reviewed evidenced tensions.');
        }

        return new NpoSocialEnterpriseDualInputs($client, $scorecard, $analysis);
    }

    /** @return list<ReportSectionDraft> */
    private function sections(NpoSocialEnterpriseScorecard $scorecard, NpoTensionAnalysis $analysis): array
    {
        return [
            ReportSectionDraft::generated(
                key: 'dual_scorecard',
                title: 'Dual impact scorecard',
                body: sprintf(
                    "Commercial score: %s/100 (%s%% weight)\nMission score: %s/100 (%s%% weight)\nBlended score: %s/100",
                    $scorecard->commercial_score,
                    $scorecard->commercial_weight,
                    $scorecard->mission_score,
                    $scorecard->mission_weight,
                    number_format((float) $scorecard->blended_score, 2),
                ),
                sourceReference: 'npo_social_enterprise_scorecard:'.$scorecard->getKey(),
                dataQualityNote: 'Data quality note: the blended score divides weighted commercial and mission scores by 100.',
                metadata: [
                    'scorecard_id' => (string) $scorecard->getKey(),
                    'commercial_axis_count' => count($scorecard->commercial_axes),
                    'mission_axis_count' => count($scorecard->mission_axes),
                ],
            ),
            ReportSectionDraft::generated(
                key: 'evidenced_tensions',
                title: 'Evidenced tensions',
                body: collect($analysis->tensions)
                    ->filter(fn (mixed $tension): bool => is_array($tension))
                    ->map(fn (array $tension): string => sprintf(
                        "%s\nCommercial: %s\nMission: %s\nRecommended path: %s",
                        (string) ($tension['title'] ?? 'Social enterprise tension'),
                        (string) ($tension['commercial_implication'] ?? 'n/a'),
                        (string) ($tension['mission_implication'] ?? 'n/a'),
                        (string) ($tension['advisor_recommended_path'] ?? 'n/a'),
                    ))
                    ->implode("\n\n"),
                sourceReference: 'npo_tension_analyses:'.$analysis->getKey(),
                dataQualityNote: 'Data quality note: every tension has advisor-reviewed data points before report generation.',
                metadata: [
                    'tension_analysis_id' => (string) $analysis->getKey(),
                    'tension_count' => count($analysis->tensions),
                ],
            ),
            ReportSectionDraft::generated(
                key: 'tension_evidence',
                title: 'Tension evidence',
                body: collect($analysis->tensions)
                    ->filter(fn (mixed $tension): bool => is_array($tension))
                    ->flatMap(function (array $tension): array {
                        $dataPoints = $tension['data_points'] ?? [];

                        return is_array($dataPoints) ? $dataPoints : [];
                    })
                    ->map(fn (mixed $point): string => is_array($point)
                        ? ((string) ($point['label'] ?? $point['key'] ?? 'Data point')).': '.(string) ($point['value'] ?? '').' ('.(string) ($point['source_reference'] ?? 'source').')'
                        : (string) $point)
                    ->implode("\n"),
                sourceReference: 'npo_tension_analyses:'.$analysis->getKey().':data_points',
                dataQualityNote: 'Data quality note: tension evidence is copied from the reviewed analysis record.',
                metadata: ['tension_analysis_id' => (string) $analysis->getKey()],
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

    /** @param Closure(Report): array<string, int|string> $after */
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
}
