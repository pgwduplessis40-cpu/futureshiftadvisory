<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AnalysisLens;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessPlan;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\FinancialSnapshot;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\NpoEngagement;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\User;
use App\Models\WebsiteAuditSnapshot;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\PvWaterfallReportChart;
use App\Services\Reports\Contracts\AcquisitionGoNoGoReportComposition;
use App\Services\Reports\Contracts\DueDiligenceReportComposition;
use App\Services\Reports\Contracts\EntrepreneurAssessmentReportComposition;
use App\Services\Reports\Contracts\NpoFunderAccountabilityReportComposition;
use App\Services\Reports\Contracts\NpoGovernanceReviewReportComposition;
use App\Services\Reports\Contracts\NpoHealthReportComposition;
use App\Services\Reports\Contracts\NpoImpactSummaryReportComposition;
use App\Services\Reports\Contracts\NpoSocialEnterpriseDualReportComposition;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Reports\Contracts\SuccessionValueGapReportComposition;
use App\Services\Reports\Contracts\ValuationReportComposition;
use App\Services\Reports\Data\NpoImpactSummaryInput;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReportComposer implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['dd.risk_register', 'dd.price_adjustment'];
    }

    public function __construct(
        private readonly ReportArtifactRenderer $artifacts,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly PvWaterfallReportChart $chart,
        private readonly AuditWriter $audit,
        private readonly AcquisitionPlanRequirements $acquisitionPlanRequirements,
        private readonly ReportTemplateCatalog $templateCatalog,
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
    ) {}

    public function compose(Client $client, ReportType $type, ?User $actor = null): Report
    {
        if (! in_array($type, [ReportType::Client, ReportType::Advisor, ReportType::Stakeholder, ReportType::Trajectory], true)) {
            throw new InvalidArgumentException("Report type [{$type->value}] is scaffolded but not composed in Phase 2 yet.");
        }

        return DB::transaction(function () use ($client, $type, $actor): Report {
            $findings = $this->findings($client);
            $waterfall = $this->waterfalls->forClient($client);
            $valuation = $this->latestValuation($client);
            $proposal = $this->latestProposal($client);
            $template = $this->templateCatalog->activeFor($type);

            $clientReleaseGate = $type === ReportType::Client && $this->standardAdvisoryClient($client);
            $reviewStatus = match (true) {
                $type === ReportType::Trajectory,
                $clientReleaseGate => 'pending_review',
                default => 'not_required',
            };

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'type' => $type,
                'title' => $type->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_2',
                    'client_release_gate' => $clientReleaseGate,
                    'redactions' => $type === ReportType::Client
                        ? ['recommendations', 'fee_detail']
                        : ($type === ReportType::Stakeholder ? ['fsa_methodology', 'fsa_ip'] : []),
                    'scaffolded_report_types' => [
                        ReportType::Stakeholder->value,
                        ReportType::Trajectory->value,
                        ReportType::DueDiligence->value,
                        ReportType::EntrepreneurAssessment->value,
                    ],
                    'template' => $this->templateCatalog->metadata($template),
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => $reviewStatus,
            ]);

            foreach ($this->sections($client, $type, $findings, $waterfall, $valuation, $proposal) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'report.generated',
                fn (Report $rendered): array => [
                    'type' => $type->value,
                    'sections' => $rendered->sections()->count(),
                    'pdf_path' => $rendered->pdf_path,
                    'pptx_path' => $rendered->pptx_path,
                ],
                ['client', 'sections'],
                $type === ReportType::Stakeholder,
            );

            return $report->refresh()->load('sections');
        });
    }

    public function composeDueDiligence(DdEngagement $engagement, ?User $actor = null): Report
    {
        return $this->dueDiligenceReports->compose($engagement, $actor);
    }

    public function composePostAcquisitionGap(PostAcquisitionMigration $migration, ?User $actor = null): Report
    {
        $migration->loadMissing([
            'advisoryClient',
            'buyerClient',
            'businessPlan.phases.sections',
            'ddReport',
            'engagement',
            'gapQuestionnaireResponse.answers.question',
            'proposal.feeCalculation',
        ]);

        $client = $migration->advisoryClient;
        $engagement = $migration->engagement;

        if (! $client instanceof Client || ! $engagement instanceof DdEngagement) {
            throw new InvalidArgumentException('Post-acquisition gap reports require a migration, advisory client, and DD engagement.');
        }

        return DB::transaction(function () use ($migration, $client, $engagement, $actor): Report {
            $risks = DdRiskRegisterItem::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->orderBy('rank')
                ->get();
            $integrationPlan = DdIntegrationPlanItem::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->orderBy('day')
                ->get();
            $plan = $migration->businessPlan;
            $requirements = $plan instanceof BusinessPlan ? $this->acquisitionPlanRequirements->payload($plan) : [];
            $completion = $plan instanceof BusinessPlan
                ? $this->acquisitionPlanRequirements->completion($plan, $requirements)
                : ['complete' => false, 'missing' => collect($this->acquisitionPlanRequirements->templatePayload())
                    ->flatMap(fn (array $phase): array => collect($phase['requirements'] ?? [])
                        ->map(fn (array $requirement): string => $phase['title'].': '.$requirement['title'])
                        ->values()
                        ->all())
                    ->values()
                    ->all()];

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'type' => ReportType::PostAcquisitionGap,
                'title' => ReportType::PostAcquisitionGap->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'post_acquisition_migration_id' => $migration->getKey(),
                    'dd_engagement_id' => $engagement->getKey(),
                    'buyer_client_id' => $migration->buyer_client_id,
                    'business_plan_id' => $plan?->getKey(),
                    'dd_pv_baseline' => $migration->dd_pv_baseline,
                    'redactions' => [],
                ],
                'render_status' => Report::RENDER_STATUS_COMPOSING,
                'review_status' => 'not_required',
            ]);

            foreach ($this->postAcquisitionGapSections($migration, $risks, $integrationPlan, $plan, $requirements, $completion) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndAuditAfterCommit(
                $report,
                $actor,
                'post_acquisition.gap_report_generated',
                fn (Report $rendered): array => [
                    'post_acquisition_migration_id' => $migration->getKey(),
                    'dd_engagement_id' => $engagement->getKey(),
                    'sections' => $rendered->sections()->count(),
                    'missing_plan_requirements' => $completion['missing'],
                    'pdf_path' => $rendered->pdf_path,
                ],
                ['client', 'sections'],
            );

            return $report->refresh()->load('sections');
        });
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

    public function usesCurrentTemplate(Report $report): bool
    {
        return $this->artifacts->usesCurrentTemplate($report);
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

    private function standardAdvisoryClient(Client $client): bool
    {
        $type = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $type === EngagementType::STANDARD_ADVISORY;
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
                    'npo_engagement_id' => $report->npo_engagement_id,
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

    /**
     * @return Collection<int, AnalysisFinding>
     */
    private function findings(Client $client): Collection
    {
        return AnalysisFinding::query()
            ->with('run')
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(80)
            ->get()
            ->sortBy(fn (AnalysisFinding $finding): string => sprintf(
                '%d-%s',
                $this->lensPosition($finding->lens),
                $finding->created_at?->toIso8601String() ?? '',
            ))
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function sections(
        Client $client,
        ReportType $type,
        Collection $findings,
        array $waterfall,
        ?BusinessValuation $valuation,
        ?Proposal $proposal,
    ): array {
        return match ($type) {
            ReportType::Client => $this->clientSections($client, $findings, $waterfall, $valuation),
            ReportType::Advisor => $this->advisorSections($client, $findings, $waterfall, $valuation, $proposal),
            ReportType::Stakeholder => $this->stakeholderSections($client, $findings, $waterfall, $valuation),
            ReportType::Trajectory => $this->trajectorySections($client, $findings),
            default => [],
        };
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function clientSections(Client $client, Collection $findings, array $waterfall, ?BusinessValuation $valuation): array
    {
        $visibleFindings = $findings
            ->reject(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
            ->values();

        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
        ];

        $websiteReview = $this->websiteReviewSection($client);
        if ($websiteReview !== null) {
            $sections[] = $websiteReview;
        }

        $whatIsWrong = $this->whatIsWrongSection($client, $visibleFindings);
        if ($whatIsWrong !== null) {
            $sections[] = $whatIsWrong;
        }

        $visibleFindings
            ->each(function (AnalysisFinding $finding) use (&$sections): void {
                $sections[] = $this->findingSection($finding);
            });

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function advisorSections(
        Client $client,
        Collection $findings,
        array $waterfall,
        ?BusinessValuation $valuation,
        ?Proposal $proposal,
    ): array {
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
            $this->waterfallSection($client, $waterfall),
        ];

        $websiteReview = $this->websiteReviewSection($client);
        if ($websiteReview !== null) {
            $sections[] = $websiteReview;
        }

        $findings->each(function (AnalysisFinding $finding) use (&$sections): void {
            $sections[] = $this->findingSection($finding);
        });

        $sections[] = $this->implementationPlanSection($client, $findings);
        $sections[] = $this->feeProposalSection($client, $proposal);

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  array<string, mixed>  $waterfall
     * @return array<int, array<string, mixed>>
     */
    private function stakeholderSections(Client $client, Collection $findings, array $waterfall, ?BusinessValuation $valuation): array
    {
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
            $this->waterfallSection($client, $waterfall),
        ];

        $findings
            ->filter(fn (AnalysisFinding $finding): bool => in_array($finding->lens, [AnalysisLens::Diagnostic, AnalysisLens::Predictive, AnalysisLens::Prescriptive], true))
            ->each(function (AnalysisFinding $finding) use (&$sections): void {
                $sections[] = $this->findingSection($finding);
            });

        $sections[] = $this->liabilityDisclaimerSection($client);

        return $sections;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function trajectorySections(Client $client, Collection $findings): array
    {
        $snapshots = FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->orderBy('period_end')
            ->get();
        $valuations = BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->orderBy('as_at')
            ->get();

        return [
            $this->financialTrendSection($client, $snapshots),
            $this->pvMilestonesSection($client, $valuations),
            $this->goalOutcomeSection($client),
            $this->trajectoryNarrativeSection($client, $snapshots, $valuations, $findings),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function websiteReviewSection(Client $client): ?array
    {
        $snapshot = WebsiteAuditSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('fetched_at')
            ->latest()
            ->first();

        if (! $snapshot instanceof WebsiteAuditSnapshot) {
            return null;
        }

        $body = match ($snapshot->fetch_status) {
            WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL => 'Website review not performed - no website URL listed/confirmed.',
            WebsiteAuditSnapshot::STATUS_BLOCKED => 'Website review not performed - the nominated website blocked the audit through robots.txt.',
            WebsiteAuditSnapshot::STATUS_UNREACHABLE => 'Website review not performed - the nominated website could not be reached.',
            default => sprintf(
                'Verified website review completed for %s at %s. Health score: findability %s/100, credibility %s/100, conversion %s/100, technical %s/100, overall %s/100. PageSpeed measurement: %s.',
                $snapshot->root_url,
                $snapshot->fetched_at?->toIso8601String() ?? 'not recorded',
                data_get($snapshot->scores, 'findability', 'not measured'),
                data_get($snapshot->scores, 'credibility', 'not measured'),
                data_get($snapshot->scores, 'conversion', 'not measured'),
                data_get($snapshot->scores, 'technical', 'not measured'),
                data_get($snapshot->scores, 'overall', 'not measured'),
                data_get($snapshot->performance, 'measured', false) ? 'available' : 'not measured',
            ),
        };

        return $this->generatedSection(
            key: 'website_review',
            title: 'Website review',
            body: $body,
            sourceReference: 'website_audit_snapshot:'.$snapshot->getKey(),
            dataQualityNote: $snapshot->fetch_status === WebsiteAuditSnapshot::STATUS_PARTIAL
                ? 'Data quality note: website review was partial; findings are limited to fetched evidence.'
                : null,
            metadata: [
                'website_audit_snapshot_id' => $snapshot->getKey(),
                'fetch_status' => $snapshot->fetch_status,
                'scores' => $snapshot->scores,
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     * @param  array<string, array<int, array<string, mixed>>>  $requirements
     * @param  array{complete: bool, missing: array<int, string>}  $completion
     * @return array<int, array<string, mixed>>
     */
    private function postAcquisitionGapSections(
        PostAcquisitionMigration $migration,
        Collection $risks,
        Collection $integrationPlan,
        ?BusinessPlan $plan,
        array $requirements,
        array $completion,
    ): array {
        return [
            $this->postAcquisitionHandoffSummarySection($migration, $plan, $completion),
            $this->postAcquisitionDdGapsSection($migration, $risks, $integrationPlan),
            $this->postAcquisitionBusinessPlanComparisonSection($migration, $risks, $plan, $requirements, $completion),
            $this->postAcquisitionAdvisorActionsSection($migration, $plan, $completion),
        ];
    }

    /**
     * @param  array{complete: bool, missing: array<int, string>}  $completion
     * @return array<string, mixed>
     */
    private function postAcquisitionHandoffSummarySection(
        PostAcquisitionMigration $migration,
        ?BusinessPlan $plan,
        array $completion,
    ): array {
        $response = $migration->gapQuestionnaireResponse;
        $gapRemaining = $response instanceof QuestionnaireResponse && $response->submitted_at !== null
            ? 0
            : count((array) data_get($migration->metadata, 'gap_questions_remaining', []));
        $proposal = $migration->proposal;
        $proposalStatus = $proposal instanceof Proposal
            ? str_replace('_', ' ', (string) (is_string($proposal->status) ? $proposal->status : $proposal->status->value))
            : 'not generated';
        $planStatus = $plan instanceof BusinessPlan
            ? str_replace('_', ' ', (string) $plan->status)
            : 'not prepared';
        $body = sprintf(
            "Target: %s.\nDD PV baseline: %s.\nMigrated DD documents: %d.\nPost-acquisition gap questionnaire: %s.\nAcquisition business plan: %s; %d plan requirement gap(s) remain.\nProposal status: %s.",
            $migration->engagement?->target_name ?? $migration->advisoryClient?->legal_name ?? 'acquired business',
            $this->money($migration->dd_pv_baseline),
            count(is_array($migration->migrated_document_ids) ? $migration->migrated_document_ids : []),
            $gapRemaining === 0 ? 'submitted or fully prefilled' : "{$gapRemaining} client confirmation item(s) remain",
            $planStatus,
            count($completion['missing']),
            $proposalStatus,
        );

        return $this->generatedSection(
            key: 'post_acquisition_handoff_summary',
            title: 'Handoff summary',
            body: $body,
            sourceReference: 'post_acquisition_migration:'.$migration->getKey(),
            dataQualityNote: 'Data quality note: handoff summary combines DD migration metadata, client gap-questionnaire state, and linked acquisition-plan status.',
            metadata: [
                'post_acquisition_migration_id' => $migration->getKey(),
                'business_plan_id' => $plan?->getKey(),
                'proposal_id' => $proposal?->getKey(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     * @return array<string, mixed>
     */
    private function postAcquisitionDdGapsSection(
        PostAcquisitionMigration $migration,
        Collection $risks,
        Collection $integrationPlan,
    ): array {
        $riskBody = $risks->isEmpty()
            ? 'No ranked DD risk gaps were available at handoff.'
            : $risks
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '#%d %s - %s. PV cost: %s. Indicative price adjustment: %s.',
                    $risk->rank,
                    str_replace('_', ' ', $risk->risk_level),
                    $risk->title,
                    $this->money($risk->pv_of_cost),
                    $this->money($risk->price_adjustment_nzd),
                ))
                ->implode("\n");
        $integrationBody = $integrationPlan->isEmpty()
            ? 'No 100-day integration actions were generated from DD yet.'
            : $integrationPlan
                ->map(fn (DdIntegrationPlanItem $item): string => sprintf(
                    'Day %d %s - %s (%s priority).',
                    $item->day,
                    $item->phase,
                    $item->action,
                    $item->priority,
                ))
                ->implode("\n");

        return $this->generatedSection(
            key: 'post_acquisition_dd_gaps',
            title: 'DD gaps requiring advisory attention',
            body: "Ranked DD gaps:\n{$riskBody}\n\nIntegration actions from DD:\n{$integrationBody}",
            sourceReference: 'dd_gap_sources:'.$migration->dd_engagement_id,
            dataQualityNote: 'Data quality note: DD gaps come from persisted DD risk-register rows and generated integration-plan actions.',
            metadata: [
                'risk_register_ids' => $risks->pluck('id')->values()->all(),
                'integration_plan_ids' => $integrationPlan->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  array<string, array<int, array<string, mixed>>>  $requirements
     * @param  array{complete: bool, missing: array<int, string>}  $completion
     * @return array<string, mixed>
     */
    private function postAcquisitionBusinessPlanComparisonSection(
        PostAcquisitionMigration $migration,
        Collection $risks,
        ?BusinessPlan $plan,
        array $requirements,
        array $completion,
    ): array {
        if (! $plan instanceof BusinessPlan) {
            $body = "No acquisition business plan is linked to this handoff yet.\nPending plan gaps:\n".implode("\n", $completion['missing']);

            return $this->generatedSection(
                key: 'post_acquisition_plan_comparison',
                title: 'DD to business-plan gap comparison',
                body: $body,
                sourceReference: 'post_acquisition_plan:none:'.$migration->getKey(),
                dataQualityNote: 'Data quality note: this comparison is template-only until the DD acquisition business plan is populated.',
                metadata: [
                    'missing_requirements' => $completion['missing'],
                ],
            );
        }

        $completeRequirements = collect($requirements)
            ->flatMap(fn (array $phaseRequirements): array => collect($phaseRequirements)
                ->filter(fn (array $requirement): bool => (bool) $requirement['complete'])
                ->map(fn (array $requirement): string => $requirement['phase_title'].': '.$requirement['title'])
                ->values()
                ->all())
            ->values()
            ->all();
        $uncoveredRisks = $this->postAcquisitionUncoveredRiskTitles($risks, $plan);
        $body = sprintf(
            "Business plan status: %s.\nCompleted plan requirements:\n%s\n\nPending plan requirements:\n%s\n\nDD risks not explicitly referenced in the plan by risk title:\n%s",
            str_replace('_', ' ', (string) $plan->status),
            $completeRequirements === [] ? 'None yet.' : implode("\n", $completeRequirements),
            $completion['missing'] === [] ? 'None.' : implode("\n", $completion['missing']),
            $uncoveredRisks === [] ? 'None detected by title match.' : implode("\n", $uncoveredRisks),
        );

        return $this->generatedSection(
            key: 'post_acquisition_plan_comparison',
            title: 'DD to business-plan gap comparison',
            body: $body,
            sourceReference: 'business_plan:'.$plan->getKey(),
            dataQualityNote: 'Data quality note: plan comparison checks the DD acquisition-plan requirement template and whether ranked DD risk titles appear in completed plan sections.',
            metadata: [
                'business_plan_id' => $plan->getKey(),
                'missing_requirements' => $completion['missing'],
                'complete_requirements' => $completeRequirements,
                'uncovered_risk_titles' => $uncoveredRisks,
            ],
        );
    }

    /**
     * @param  array{complete: bool, missing: array<int, string>}  $completion
     * @return array<string, mixed>
     */
    private function postAcquisitionAdvisorActionsSection(
        PostAcquisitionMigration $migration,
        ?BusinessPlan $plan,
        array $completion,
    ): array {
        $actions = [];
        $response = $migration->gapQuestionnaireResponse;
        $proposal = $migration->proposal;
        $proposalStatus = $proposal instanceof Proposal
            ? (is_string($proposal->status) ? $proposal->status : $proposal->status->value)
            : null;

        if (! $response instanceof QuestionnaireResponse || $response->submitted_at === null) {
            $actions[] = 'Ask the client to complete the post-acquisition gap questionnaire and confirm the DD-prefilled answers.';
        }

        if (! $plan instanceof BusinessPlan) {
            $actions[] = 'Prepare or link the DD acquisition business plan before finalising post-acquisition advice.';
        } elseif (! $completion['complete']) {
            $actions[] = 'Resolve remaining plan gaps: '.implode('; ', $completion['missing']).'.';
        }

        if ($proposalStatus === 'draft') {
            $actions[] = 'Review and release the generated post-acquisition proposal so the client can sign off.';
        } elseif ($proposalStatus === null) {
            $actions[] = 'Generate a post-acquisition advisory proposal once scope and gaps are confirmed.';
        }

        if ($actions === []) {
            $actions[] = 'Proceed with advisor-led post-acquisition advisory scoping and first 100-day implementation planning.';
        }

        return $this->generatedSection(
            key: 'post_acquisition_advisor_actions',
            title: 'Advisor action list',
            body: implode("\n", $actions),
            sourceReference: 'post_acquisition_actions:'.$migration->getKey(),
            dataQualityNote: 'Data quality note: action list reflects current persisted workflow state and should be reviewed by the advisor before client advice is issued.',
            metadata: [
                'actions' => $actions,
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<int, string>
     */
    private function postAcquisitionUncoveredRiskTitles(Collection $risks, BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');
        $planText = Str::lower($plan->phases
            ->flatMap(fn ($phase) => $phase->sections)
            ->filter(fn ($section): bool => $section instanceof PlanSection)
            ->map(fn (PlanSection $section): string => $section->title."\n".$section->body)
            ->implode("\n"));

        return $risks
            ->filter(function (DdRiskRegisterItem $risk) use ($planText): bool {
                $title = Str::lower(trim($risk->title));

                return $title !== '' && ! str_contains($planText, $title);
            })
            ->pluck('title')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $waterfall
     * @return array<string, mixed>
     */
    private function valuationSection(Client $client, array $waterfall, ?BusinessValuation $valuation): array
    {
        $body = $valuation instanceof BusinessValuation
            ? sprintf(
                "Current valuation range is NZD %s to NZD %s, with reconciled midpoint NZD %s.\n%s",
                number_format($valuation->reconciled_low, 0),
                number_format($valuation->reconciled_high, 0),
                number_format($valuation->reconciled_mid, 0),
                $this->metricExplanation('valuation_range'),
            )
            : sprintf(
                "Current PV is NZD %s and modelled upside PV is NZD %s from the latest platform waterfall, with a planning range of NZD %s to NZD %s.\n%s",
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
                number_format((float) data_get($waterfall, 'target_pv_range.low', $waterfall['target_pv']), 0),
                number_format((float) data_get($waterfall, 'target_pv_range.high', $waterfall['target_pv']), 0),
                $this->metricExplanation('pv'),
            );

        return $this->generatedSection(
            key: 'valuation',
            title: 'Current valuation range',
            body: $body,
            sourceReference: 'pv_waterfall:'.$client->getKey(),
            dataQualityNote: 'Data quality note: valuation figures come from persisted PV and valuation rows.',
        );
    }

    /**
     * @param  array<string, mixed>  $waterfall
     * @return array<string, mixed>
     */
    private function waterfallSection(Client $client, array $waterfall): array
    {
        return $this->generatedSection(
            key: 'pv_waterfall',
            title: 'PV waterfall',
            body: sprintf(
                "The advisor view includes current PV NZD %s, improvements NZD %s, risk mitigation NZD %s, and modelled upside PV NZD %s. The modelled upside range is NZD %s to NZD %s and assumes surfaced improvements and risk mitigations are fully captured.\n%s\n%s",
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['improvement_pv'], 0),
                number_format((float) $waterfall['risk_mitigation_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
                number_format((float) data_get($waterfall, 'target_pv_range.low', $waterfall['target_pv']), 0),
                number_format((float) data_get($waterfall, 'target_pv_range.high', $waterfall['target_pv']), 0),
                $this->metricExplanation('pv'),
                $this->metricExplanation('modelled_upside'),
            ),
            sourceReference: 'pv_waterfall:'.$client->getKey(),
            dataQualityNote: 'Data quality note: waterfall values are assembled from the latest persisted PV rows.',
            metadata: [
                'chart_html' => $this->chart->render($waterfall['waterfall']),
                'waterfall' => $waterfall,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function findingSection(AnalysisFinding $finding): array
    {
        return [
            'key' => 'finding_'.$finding->getKey(),
            'title' => $finding->title,
            'body' => 'Key takeaway: '.$this->keyTakeaway($finding)."\n\n".$finding->body,
            'lens' => $finding->lens->value,
            'attributions' => $this->attributions($finding),
            'document_support' => $finding->document_support,
            'document_support_note' => $this->documentSupportNote($finding->document_support),
            'data_quality_note' => $finding->data_quality_disclaimer ?: '',
            'metadata' => [
                'analysis_finding_id' => $finding->getKey(),
                'severity' => $finding->severity->value,
                'key_takeaway' => $this->keyTakeaway($finding),
                'module' => $finding->run?->module?->value,
            ],
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>
     */
    private function implementationPlanSection(Client $client, Collection $findings): array
    {
        $prescriptive = $findings
            ->filter(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
            ->values();

        $body = $prescriptive->isEmpty()
            ? 'No prescriptive implementation findings have been generated yet.'
            : $prescriptive
                ->map(fn (AnalysisFinding $finding): string => $finding->title.': '.$finding->body)
                ->implode("\n\n");

        return $this->generatedSection(
            key: 'implementation_plan',
            title: 'Implementation plan',
            body: $body,
            sourceReference: 'analysis_findings:'.$client->getKey().':prescriptive',
            documentSupport: $this->strongestDocumentSupport($prescriptive),
            dataQualityNote: $this->combinedDataQualityNote($prescriptive),
            metadata: [
                'prescriptive_finding_ids' => $prescriptive->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>|null
     */
    private function whatIsWrongSection(Client $client, Collection $findings): ?array
    {
        $diagnosticFindings = $findings
            ->filter(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Diagnostic)
            ->values();

        $selected = $diagnosticFindings->isNotEmpty()
            ? $diagnosticFindings
            : $findings
                ->filter(fn (AnalysisFinding $finding): bool => in_array($finding->lens, [AnalysisLens::Predictive, AnalysisLens::Descriptive], true))
                ->values();

        if ($selected->isEmpty()) {
            return null;
        }

        $body = $selected
            ->sortByDesc(fn (AnalysisFinding $finding): int => $this->severityRank($finding->severity))
            ->take(8)
            ->map(fn (AnalysisFinding $finding): string => sprintf(
                '[%s] %s: %s',
                $this->severityLabel($finding->severity),
                $finding->title,
                $this->keyTakeaway($finding),
            ))
            ->implode("\n\n");

        return $this->generatedSection(
            key: 'what_is_wrong',
            title: 'What is wrong',
            body: $body,
            sourceReference: 'analysis_findings:'.$client->getKey().':what_is_wrong',
            documentSupport: $this->strongestDocumentSupport($selected),
            dataQualityNote: $this->combinedDataQualityNote($selected),
            metadata: [
                'diagnostic_finding_ids' => $selected
                    ->sortByDesc(fn (AnalysisFinding $finding): int => $this->severityRank($finding->severity))
                    ->take(8)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
        );
    }

    private function keyTakeaway(AnalysisFinding $finding): string
    {
        $body = trim((string) $finding->body);
        $normalised = trim((string) preg_replace('/\s+/', ' ', $body));
        $parts = preg_split('/(?<=[.!?])\s+/', $normalised, 2);
        $firstSentence = trim((string) ($parts[0] ?? ''));

        if ($firstSentence === '') {
            return $finding->title;
        }

        return Str::limit($firstSentence, 180, '');
    }

    private function severityRank(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::Critical => 5,
            FindingSeverity::High => 4,
            FindingSeverity::Medium => 3,
            FindingSeverity::Low => 2,
            FindingSeverity::Info => 1,
        };
    }

    private function severityLabel(FindingSeverity $severity): string
    {
        return Str::headline($severity->value);
    }

    private function metricExplanation(string $metric): string
    {
        return match ($metric) {
            'pv' => "PV (present value) means future benefits expressed as a single today's-dollars figure.",
            'modelled_upside' => 'Modelled upside is the value the plan could unlock if the identified improvements and risk reductions are actually achieved.',
            'valuation_range' => 'A valuation range gives a low-to-high planning view; the midpoint is a guide, not a guaranteed sale price.',
            'roi' => 'ROI compares modelled value with the advisory fee; for example, 3.25 means every NZD 1 of fee is modelled to unlock NZD 3.25 of value.',
            'goal_target' => 'A goal target is the value outcome the advisor and client agree to track over time.',
            default => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function feeProposalSection(Client $client, ?Proposal $proposal): array
    {
        if (! $proposal instanceof Proposal) {
            return $this->generatedSection(
                key: 'fee_proposal',
                title: 'Fee proposal and ROI',
                body: 'No proposal has been generated yet.',
                sourceReference: 'proposal:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: fee proposal data is not available.',
            );
        }

        $fee = $proposal->feeCalculation;
        $body = sprintf(
            'Latest proposal v%s has suggested midpoint fee NZD %s. For every NZD 1 of advisory fee, the model shows NZD %s of potential value. Proposal status: %s.',
            $proposal->version,
            number_format($fee?->suggested_mid ?? 0, 0),
            number_format($proposal->roi_ratio, 2),
            Str::headline($proposal->status->value),
        );

        return $this->generatedSection(
            key: 'fee_proposal',
            title: 'Fee proposal and ROI',
            body: $body,
            sourceReference: 'proposal:'.$proposal->getKey(),
            dataQualityNote: 'Data quality note: fee and ROI values come from the selected proposal and fee calculation.',
            metadata: [
                'proposal_id' => $proposal->getKey(),
                'fee_calculation_id' => $proposal->fee_calculation_id,
                'roi_ratio' => $proposal->roi_ratio,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function liabilityDisclaimerSection(Client $client): array
    {
        return $this->generatedSection(
            key: 'liability_disclaimer',
            title: 'Liability disclaimer',
            body: 'This stakeholder report is prepared for discussion and decision support only. It does not replace financial, legal, tax, lending, investment, or governance advice. Recipients should rely on their own professional advisers before acting on the information.',
            sourceReference: 'stakeholder_disclaimer:'.$client->getKey(),
            dataQualityNote: 'Data quality note: the disclaimer applies to every exported stakeholder report.',
        );
    }

    /**
     * @param  Collection<int, FinancialSnapshot>  $snapshots
     * @return array<string, mixed>
     */
    private function financialTrendSection(Client $client, Collection $snapshots): array
    {
        $first = $snapshots->first();
        $latest = $snapshots->last();

        if (! $first instanceof FinancialSnapshot || ! $latest instanceof FinancialSnapshot) {
            return $this->generatedSection(
                key: 'financial_trends',
                title: 'Start to current metrics',
                body: 'Financial trend snapshots are not available yet.',
                sourceReference: 'financial_snapshots:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: trend analysis is pending connected or imported financial snapshots.',
            );
        }

        $metrics = collect(['revenue', 'gross_margin', 'cash_balance', 'debtor_days'])
            ->map(function (string $metric) use ($first, $latest): string {
                $start = data_get($first->metrics, $metric);
                $current = data_get($latest->metrics, $metric);

                return sprintf('%s: %s -> %s', str($metric)->replace('_', ' ')->title(), $this->formatMetric($start), $this->formatMetric($current));
            })
            ->implode("\n");

        return $this->generatedSection(
            key: 'financial_trends',
            title: 'Start to current metrics',
            body: sprintf(
                "Engagement start period: %s\nCurrent period: %s\n%s",
                $first->period_end?->toDateString() ?? 'n/a',
                $latest->period_end?->toDateString() ?? 'n/a',
                $metrics,
            ),
            sourceReference: 'financial_snapshots:'.$first->getKey().':'.$latest->getKey(),
            dataQualityNote: 'Data quality note: trend values compare earliest and latest persisted financial snapshots.',
            metadata: [
                'start_snapshot_id' => $first->getKey(),
                'current_snapshot_id' => $latest->getKey(),
            ],
        );
    }

    /**
     * @param  Collection<int, BusinessValuation>  $valuations
     * @return array<string, mixed>
     */
    private function pvMilestonesSection(Client $client, Collection $valuations): array
    {
        if ($valuations->isEmpty()) {
            return $this->generatedSection(
                key: 'pv_milestones',
                title: 'PV milestones',
                body: 'PV milestones are not available yet.',
                sourceReference: 'business_valuations:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: milestone analysis is pending persisted valuations.',
            );
        }

        $body = $valuations
            ->map(fn (BusinessValuation $valuation): string => sprintf(
                '%s: NZD %s midpoint',
                $valuation->as_at?->toDateString() ?? 'undated',
                number_format($valuation->reconciled_mid, 0),
            ))
            ->implode("\n");

        return $this->generatedSection(
            key: 'pv_milestones',
            title: 'PV milestones',
            body: $body,
            sourceReference: 'business_valuations:'.$client->getKey(),
            dataQualityNote: 'Data quality note: milestones are based on persisted business valuation rows.',
            metadata: [
                'valuation_ids' => $valuations->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function goalOutcomeSection(Client $client): array
    {
        $goals = Goal::query()
            ->with(['baselineBusinessValuation', 'latestBusinessValuation', 'milestones'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->get();

        if ($goals->isEmpty()) {
            return $this->generatedSection(
                key: 'goal_outcomes',
                title: 'Goal outcome measurement',
                body: 'No PV-linked goals have been recorded yet.',
                sourceReference: 'goals:none:'.$client->getKey(),
                dataQualityNote: 'Data quality note: goal outcome measurement starts once advisor goals have a baseline and target.',
            );
        }

        $body = $goals
            ->map(function (Goal $goal): string {
                $baseline = $goal->baselineBusinessValuation;
                $current = $goal->latestBusinessValuation;
                $baselineValue = $baseline instanceof BusinessValuation ? $baseline->reconciled_mid : null;
                $currentValue = $current instanceof BusinessValuation ? $current->reconciled_mid : null;
                $target = (float) $goal->pv_target;
                $gap = $currentValue === null || $target <= 0
                    ? null
                    : $target - $currentValue;
                $realised = round((float) $goal->milestones
                    ->where('status', Milestone::STATUS_COMPLETED)
                    ->sum('pv_of_impact'), 2);

                return sprintf(
                    '%s: baseline %s; current %s; target %s%s; verified milestone PV %s; status %s.',
                    $goal->title,
                    $baselineValue === null ? 'not measured' : 'NZD '.number_format($baselineValue, 0),
                    $currentValue === null ? 'not re-measured' : 'NZD '.number_format($currentValue, 0),
                    $target > 0 ? 'NZD '.number_format($target, 0) : 'not set',
                    $gap === null ? '' : '; gap '.($gap <= 0 ? 'met by NZD '.number_format(abs($gap), 0) : 'NZD '.number_format($gap, 0)),
                    'NZD '.number_format($realised, 0),
                    str_replace('_', ' ', $goal->status),
                );
            })
            ->implode("\n");

        return $this->generatedSection(
            key: 'goal_outcomes',
            title: 'Goal outcome measurement',
            body: $body,
            sourceReference: 'goals:'.$client->getKey(),
            dataQualityNote: 'Data quality note: baseline and current PV use persisted business valuations; verified milestone PV explains the evidence-backed contribution and still requires advisor review.',
            metadata: [
                'goal_ids' => $goals->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, FinancialSnapshot>  $snapshots
     * @param  Collection<int, BusinessValuation>  $valuations
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>
     */
    private function trajectoryNarrativeSection(Client $client, Collection $snapshots, Collection $valuations, Collection $findings): array
    {
        $firstValuation = $valuations->first();
        $latestValuation = $valuations->last();
        $pvChange = $firstValuation instanceof BusinessValuation && $latestValuation instanceof BusinessValuation
            ? $latestValuation->reconciled_mid - $firstValuation->reconciled_mid
            : null;
        $currentFindingTitles = $findings
            ->take(3)
            ->pluck('title')
            ->implode('; ');
        $body = sprintf(
            "Auto-generated narrative for advisor review.\nSnapshots reviewed: %s.\nPV movement: %s.\nCurrent focus: %s.",
            $snapshots->count(),
            $pvChange === null ? 'not enough valuation milestones' : 'NZD '.number_format($pvChange, 0),
            $currentFindingTitles !== '' ? $currentFindingTitles : 'no current findings',
        );

        return $this->generatedSection(
            key: 'trajectory_narrative',
            title: 'Advisor-reviewed trajectory narrative',
            body: $body,
            sourceReference: 'trajectory_report:'.$client->getKey(),
            dataQualityNote: 'Data quality note: narrative is generated from persisted report inputs and requires advisor review before sharing.',
            metadata: [
                'advisor_review_required' => true,
            ],
        );
    }

    private function autoReleaseAt(Report $report): ?Carbon
    {
        $value = $report->metadata['auto_release_at'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function generatedSection(
        string $key,
        string $title,
        string $body,
        string $sourceReference,
        string $documentSupport = AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        ?string $dataQualityNote = null,
        array $metadata = [],
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'body' => $body,
            'lens' => null,
            'attributions' => [[
                'claim' => $title,
                'source_reference' => $sourceReference,
            ]],
            'document_support' => $documentSupport,
            'document_support_note' => $this->documentSupportNote($documentSupport),
            'data_quality_note' => $dataQualityNote ?: '',
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function attributions(AnalysisFinding $finding): array
    {
        $attributions = is_array($finding->attributions) ? $finding->attributions : [];
        $normalised = collect($attributions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'claim' => (string) ($item['claim'] ?? $finding->title),
                'source_reference' => (string) ($item['source_reference'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['source_reference'] !== '')
            ->values()
            ->all();

        if ($normalised !== []) {
            return $normalised;
        }

        return [[
            'claim' => $finding->title,
            'source_reference' => 'analysis_finding:'.$finding->getKey(),
        ]];
    }

    private function documentSupportNote(string $support): string
    {
        return match ($support) {
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED => 'Backed by verified documents you uploaded.',
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG => 'Includes a document point that needs advisor review.',
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY => 'Includes a document discrepancy that needs resolution.',
            default => '',
        };
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
    private function strongestDocumentSupport(Collection $findings): string
    {
        foreach ([
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY,
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG,
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED,
        ] as $support) {
            if ($findings->contains(fn (AnalysisFinding $finding): bool => $finding->document_support === $support)) {
                return $support;
            }
        }

        return AnalysisFinding::DOCUMENT_SUPPORT_NONE;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
    private function combinedDataQualityNote(Collection $findings): string
    {
        $notes = $findings
            ->pluck('data_quality_disclaimer')
            ->filter(fn (mixed $note): bool => is_string($note) && trim($note) !== '')
            ->unique()
            ->values();

        return $notes->isEmpty()
            ? ''
            : $notes->implode("\n");
    }

    private function latestValuation(Client $client): ?BusinessValuation
    {
        return BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }

    private function latestProposal(Client $client): ?Proposal
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    private function lensPosition(AnalysisLens $lens): int
    {
        return match ($lens) {
            AnalysisLens::Descriptive => 1,
            AnalysisLens::Diagnostic => 2,
            AnalysisLens::Predictive => 3,
            AnalysisLens::Prescriptive => 4,
        };
    }

    private function formatMetric(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'n/a';
        }

        return number_format((float) $value, abs((float) $value) < 1 ? 2 : 0);
    }

    /**
     * @param  callable(Report): array<string, bool|float|int|string|array<int, string>>  $after
     * @param  array<int, string>  $load
     */
    private function renderAndAuditAfterCommit(
        Report $report,
        ?User $actor,
        string $action,
        callable $after,
        array $load,
        bool $withPptx = false,
    ): void {
        $callback = function () use ($report, $actor, $action, $after, $load, $withPptx): void {
            $report->refresh()->load($load);
            $this->artifacts->render($report, $withPptx);
            $this->audit->record($action, subject: $report, actor: $actor, after: $after($report->refresh()));
        };

        if (app()->environment('testing') && DB::transactionLevel() > 1) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }
}
