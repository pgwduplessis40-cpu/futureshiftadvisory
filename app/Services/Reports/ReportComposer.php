<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Jobs\RerenderReportArtifacts;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\DdEngagement;
use App\Models\NpoEngagement;
use App\Models\PlanAssessment;
use App\Models\PostAcquisitionMigration;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\Contracts\AcquisitionGoNoGoReportComposition;
use App\Services\Reports\Contracts\DueDiligenceReportComposition;
use App\Services\Reports\Contracts\EntrepreneurAssessmentReportComposition;
use App\Services\Reports\Contracts\NpoFunderAccountabilityReportComposition;
use App\Services\Reports\Contracts\NpoGovernanceReviewReportComposition;
use App\Services\Reports\Contracts\NpoHealthReportComposition;
use App\Services\Reports\Contracts\NpoImpactSummaryReportComposition;
use App\Services\Reports\Contracts\NpoSocialEnterpriseDualReportComposition;
use App\Services\Reports\Contracts\PostAcquisitionGapReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Contracts\StandardAdvisoryReportComposition;
use App\Services\Reports\Contracts\SuccessionValueGapReportComposition;
use App\Services\Reports\Contracts\ValuationReportComposition;
use App\Services\Reports\Data\NpoImpactSummaryInput;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Public report API. Each report type is composed behind its own typed contract.
 */
final class ReportComposer implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['dd.risk_register', 'dd.price_adjustment'];
    }

    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly AuditWriter $audit,
        private readonly StandardAdvisoryReportComposition $standardAdvisoryReports,
        private readonly NpoHealthReportComposition $npoHealthReports,
        private readonly NpoGovernanceReviewReportComposition $npoGovernanceReviews,
        private readonly NpoSocialEnterpriseDualReportComposition $npoSocialEnterpriseDualReports,
        private readonly NpoImpactSummaryReportComposition $npoImpactSummaryReports,
        private readonly NpoFunderAccountabilityReportComposition $npoFunderAccountabilityReports,
        private readonly EntrepreneurAssessmentReportComposition $entrepreneurAssessmentReports,
        private readonly ValuationReportComposition $valuationReports,
        private readonly SuccessionValueGapReportComposition $successionValueGapReports,
        private readonly DueDiligenceReportComposition $dueDiligenceReports,
        private readonly AcquisitionGoNoGoReportComposition $acquisitionGoNoGoReports,
        private readonly PostAcquisitionGapReportComposition $postAcquisitionGapReports,
    ) {}

    public function compose(Client $client, ReportType $type, ?User $actor = null): Report
    {
        return $this->standardAdvisoryReports->compose($client, $type, $actor);
    }

    public function composeDueDiligence(DdEngagement $engagement, ?User $actor = null): Report
    {
        return $this->dueDiligenceReports->compose($engagement, $actor);
    }

    public function composePostAcquisitionGap(PostAcquisitionMigration $migration, ?User $actor = null): Report
    {
        return $this->postAcquisitionGapReports->compose($migration, $actor);
    }

    public function composeEntrepreneurAssessment(PlanAssessment $assessment, ?User $actor = null): Report
    {
        return $this->entrepreneurAssessmentReports->compose($assessment, $actor);
    }

    public function composeGovernanceReview(NpoEngagement $engagement, ?User $actor = null): Report
    {
        return $this->npoGovernanceReviews->compose($engagement, $actor);
    }

    public function composeFunderAccountability(NpoEngagement $engagement, ?ClientFunderRecord $record = null, ?User $actor = null): Report
    {
        return $this->npoFunderAccountabilityReports->compose($engagement, $record, $actor);
    }

    public function composeNpoHealth(NpoEngagement $engagement, ?User $actor = null): Report
    {
        return $this->npoHealthReports->composeHealth($engagement, $actor);
    }

    public function composeNpoAdvisor(NpoEngagement $engagement, ?User $actor = null): Report
    {
        return $this->npoHealthReports->composeAdvisor($engagement, $actor);
    }

    public function composeSocialEnterpriseDual(NpoEngagement $engagement, ?User $actor = null): Report
    {
        return $this->npoSocialEnterpriseDualReports->compose($engagement, $actor);
    }

    public function composeImpactSummary(NpoEngagement $engagement, NpoImpactSummaryInput $input, ?User $actor = null): Report
    {
        return $this->npoImpactSummaryReports->compose($engagement, $input, $actor);
    }

    public function composeValuation(Client $client, ?User $actor = null): Report
    {
        return $this->valuationReports->compose($client, $actor);
    }

    public function composeAcquisitionGoNoGo(DdEngagement $engagement, ?User $actor = null): Report
    {
        return $this->acquisitionGoNoGoReports->compose($engagement, $actor);
    }

    public function composeSuccessionValueGap(Client $client, ?User $actor = null): Report
    {
        return $this->successionValueGapReports->compose($client, $actor);
    }

    public function markReviewed(Report $report, User $actor): Report
    {
        $report = $report->refresh();

        if (! $this->usesAdvisorReviewGate($report)) {
            throw new InvalidArgumentException('This report type does not use the advisor review gate.');
        }

        $report->forceFill([
            'review_status' => 'reviewed',
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $this->audit->record('report.reviewed', subject: $report, actor: $actor, after: [
            'type' => $report->type->value,
            'review_status' => 'reviewed',
        ]);

        return $report->refresh();
    }

    public function rerenderArtifacts(Report $report): Report
    {
        $this->artifacts->rerender($report);

        $this->audit->record('report.rerendered', subject: $report, after: [
            'type' => $report->type->value,
            'pdf_path' => $report->pdf_path,
            'pptx_path' => $report->pptx_path,
        ]);

        return $report->refresh();
    }

    /**
     * Claim a stale artifact refresh exactly once and queue it after the
     * current transaction commits. A download request must never wait for the
     * external PDF renderer.
     */
    public function queueArtifactRerender(Report $report): bool
    {
        $requestToken = (string) Str::uuid();
        $queued = DB::transaction(function () use ($report, $requestToken): bool {
            $locked = Report::query()
                ->lockForUpdate()
                ->find($report->getKey());

            if (! $locked instanceof Report || $locked->render_status === Report::RENDER_STATUS_COMPOSING) {
                return false;
            }

            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['artifact_rerender_request'] = [
                'token' => $requestToken,
                'requested_at' => now()->toIso8601String(),
            ];

            $locked->forceFill([
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'render_failed_at' => null,
                'render_error' => null,
                'metadata' => $metadata,
            ])->save();

            RerenderReportArtifacts::dispatch((string) $locked->getKey(), $requestToken)->afterCommit();

            return true;
        });

        if ($queued) {
            $this->audit->record('report.rerender_queued', subject: $report, after: [
                'type' => $report->type->value,
            ]);
        }

        return $queued;
    }

    public function rerenderQueuedArtifacts(Report $report, string $requestToken): void
    {
        $report->refresh();
        $metadata = is_array($report->metadata) ? $report->metadata : [];

        if (
            $report->render_status !== Report::RENDER_STATUS_COMPOSING
            || data_get($metadata, 'artifact_rerender_request.token') !== $requestToken
        ) {
            return;
        }

        $this->rerenderArtifacts($report);

        $metadata = is_array($report->refresh()->metadata) ? $report->metadata : [];
        unset($metadata['artifact_rerender_request']);
        $report->forceFill(['metadata' => $metadata])->save();
    }

    public function usesCurrentTemplate(Report $report): bool
    {
        return $this->artifacts->usesCurrentTemplate($report);
    }

    public function autoReleaseDueImpactSummaries(?User $actor = null): int
    {
        $released = 0;

        Report::query()
            ->where('type', ReportType::ImpactSummary->value)
            ->where('review_status', 'pending_review')
            ->orderBy('generated_at')
            ->get()
            ->each(function (Report $report) use (&$released, $actor): void {
                $autoReleaseAt = $this->autoReleaseAt($report);

                if ($autoReleaseAt === null || $autoReleaseAt->isFuture()) {
                    return;
                }

                $metadata = $report->metadata ?? [];
                $metadata['auto_released'] = true;
                $metadata['auto_released_at'] = now()->toIso8601String();

                $report->forceFill([
                    'metadata' => $metadata,
                    'review_status' => 'reviewed',
                    'reviewed_by_user_id' => $actor?->getKey(),
                    'reviewed_at' => now(),
                ])->save();

                $this->audit->record('npo.impact_summary_auto_released', subject: $report, actor: $actor, after: [
                    'npo_engagement_id' => $report->getAttribute('npo_engagement_id'),
                    'auto_release_at' => $metadata['auto_release_at'] ?? null,
                    'review_status' => 'reviewed',
                ]);

                $released++;
            });

        return $released;
    }

    public function canShareWithFunder(Report $report): bool
    {
        $report = $report->refresh();

        return $report->type === ReportType::FunderAccountability && $report->reviewed();
    }

    private function usesAdvisorReviewGate(Report $report): bool
    {
        if (in_array($report->type, [
            ReportType::DueDiligence,
            ReportType::Valuation,
            ReportType::AcquisitionGoNoGo,
            ReportType::Trajectory,
            ReportType::SuccessionValueGap,
            ReportType::FunderAccountability,
            ReportType::ImpactSummary,
        ], true)) {
            return true;
        }

        return $report->type === ReportType::Client
            && (bool) data_get($report->metadata, 'client_release_gate', false);
    }

    private function autoReleaseAt(Report $report): ?Carbon
    {
        $value = $report->metadata['auto_release_at'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
