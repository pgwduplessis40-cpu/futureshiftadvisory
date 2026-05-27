<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AnalysisLens;
use App\Enums\DiscountMethod;
use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\DocumentVerification;
use App\Models\FinancialSnapshot;
use App\Models\GovernanceReviewFinding;
use App\Models\Milestone;
use App\Models\NpoEngagement;
use App\Models\NzResource;
use App\Models\PlanAssessment;
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\RatingFramework;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\RiskCost;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdDisclaimer;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\PvWaterfallReportChart;
use App\Services\Pv\RiskCostPv;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ReportComposer implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['dd.risk_register', 'dd.price_adjustment'];
    }

    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly PptxGenerator $pptx,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly PvWaterfallReportChart $chart,
        private readonly RiskCostPv $riskCosts,
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
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

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'type' => $type,
                'title' => $type->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_2',
                    'redactions' => $type === ReportType::Client
                        ? ['recommendations', 'fee_detail']
                        : ($type === ReportType::Stakeholder ? ['fsa_methodology', 'fsa_ip'] : []),
                    'scaffolded_report_types' => [
                        ReportType::Stakeholder->value,
                        ReportType::Trajectory->value,
                        ReportType::DueDiligence->value,
                        ReportType::EntrepreneurAssessment->value,
                    ],
                ],
                'review_status' => $type === ReportType::Trajectory ? 'pending_review' : 'not_required',
            ]);

            foreach ($this->sections($client, $type, $findings, $waterfall, $valuation, $proposal) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            if ($type === ReportType::Stakeholder) {
                $this->renderAndStorePptx($report->refresh()->load(['client', 'sections']));
            }

            $this->audit->record('report.generated', subject: $report, actor: $actor, after: [
                'type' => $type->value,
                'sections' => $report->sections()->count(),
                'pdf_path' => $report->pdf_path,
                'pptx_path' => $report->pptx_path,
            ]);

            return $report->refresh()->load('sections');
        });
    }

    public function composeDueDiligence(DdEngagement $engagement, ?User $actor = null): Report
    {
        $engagement->loadMissing('client');

        return DB::transaction(function () use ($engagement, $actor): Report {
            $findings = $this->ddFindings($engagement);
            $valuation = $this->latestDdValuation($engagement);
            $risks = $this->refreshDdRiskRegister($engagement, $findings, $valuation);
            $integrationPlan = $this->refreshDdIntegrationPlan($engagement, $risks);
            $recommendation = $this->ddRecommendation($risks, $valuation);

            $engagement->forceFill([
                'recommendation' => $recommendation['recommendation'],
            ])->save();

            $report = Report::query()->create([
                'client_id' => $engagement->client_id,
                'type' => ReportType::DueDiligence,
                'title' => ReportType::DueDiligence->label().' - '.$engagement->target_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'dd_engagement_id' => $engagement->getKey(),
                    'target_name' => $engagement->target_name,
                    'recommendation' => $recommendation,
                    'redactions' => [],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->dueDiligenceSections($engagement, $findings, $valuation, $risks, $integrationPlan, $recommendation) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $engagement->client_id,
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));
            $this->renderAndStorePptx($report->refresh()->load(['client', 'sections']));

            $this->audit->record('dd.report_generated', subject: $report, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'recommendation' => $recommendation['recommendation'],
                'sections' => $report->sections()->count(),
                'risk_count' => $risks->count(),
                'pdf_path' => $report->pdf_path,
                'pptx_path' => $report->pptx_path,
            ]);

            return $report->refresh()->load('sections');
        });
    }

    public function composeEntrepreneurAssessment(PlanAssessment $assessment, ?User $actor = null): Report
    {
        $assessment->loadMissing([
            'businessPlan.entrepreneurProfile',
            'businessPlan.sections',
            'conceptPvCalculation',
            'ratingFramework.criteria',
        ]);

        return DB::transaction(function () use ($assessment, $actor): Report {
            $assessment = $assessment->refresh()->load([
                'businessPlan.entrepreneurProfile',
                'businessPlan.sections',
                'conceptPvCalculation',
                'ratingFramework.criteria',
            ]);
            $plan = $assessment->businessPlan;
            $profile = $plan?->entrepreneurProfile;

            if ($plan === null || $profile === null || $assessment->ratingFramework === null) {
                throw new InvalidArgumentException('Entrepreneur assessment reports require a plan, profile, and rating framework.');
            }

            $criteria = $this->entrepreneurCriteriaRows($assessment);
            $weightedScore = $this->weightedEntrepreneurScore($assessment->ratingFramework, $criteria);
            $overallGrade = $assessment->ratingFramework->gradeFor($weightedScore);
            $conceptPv = $assessment->conceptPvCalculation
                ?: $this->createEntrepreneurConceptPv($assessment, $weightedScore, $overallGrade, $actor);

            if ($assessment->overall_grade !== $overallGrade || $assessment->concept_pv_calculation_id !== $conceptPv->getKey()) {
                $assessment->forceFill([
                    'overall_grade' => $overallGrade,
                    'concept_pv_calculation_id' => $conceptPv->getKey(),
                ])->save();
            }

            $report = Report::query()->create([
                'client_id' => $plan->client_id,
                'entrepreneur_profile_id' => $profile->getKey(),
                'type' => ReportType::EntrepreneurAssessment,
                'title' => ReportType::EntrepreneurAssessment->label().' - '.$profile->name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_3',
                    'business_plan_id' => $plan->getKey(),
                    'plan_assessment_id' => $assessment->getKey(),
                    'assessment_round' => $assessment->round,
                    'rating_framework_id' => $assessment->rating_framework_id,
                    'overall_grade' => $overallGrade,
                    'weighted_score' => $weightedScore,
                    'concept_pv_calculation_id' => $conceptPv->getKey(),
                    'concept_pv_present_value' => data_get($conceptPv->result, 'present_value'),
                    'redactions' => [],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->entrepreneurAssessmentSections($assessment->refresh(), $criteria, $weightedScore, $overallGrade, $conceptPv) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $plan->client_id,
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'entrepreneurProfile', 'sections']));

            $this->audit->record('entrepreneur.assessment_report_generated', subject: $report, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
                'plan_assessment_id' => $assessment->getKey(),
                'overall_grade' => $overallGrade,
                'weighted_score' => $weightedScore,
                'concept_pv_calculation_id' => $conceptPv->getKey(),
                'sections' => $report->sections()->count(),
                'pdf_path' => $report->pdf_path,
            ]);

            return $report->refresh()->load(['entrepreneurProfile', 'sections']);
        });
    }

    public function composeGovernanceReview(NpoEngagement $engagement, ?User $actor = null): Report
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Governance Review reports require an NPO engagement with a client.');
        }

        if ($engagement->sub_type !== NpoEngagementSubType::GovernanceReview) {
            throw new InvalidArgumentException('Only governance-review NPO engagements can generate a Governance Review Report.');
        }

        return DB::transaction(function () use ($client, $engagement, $actor): Report {
            $findings = $this->governanceFindings($engagement);
            if ($findings->isEmpty()) {
                throw new InvalidArgumentException('Governance Review Report requires advisor-reviewed governance findings.');
            }

            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::GovernanceReview,
                'title' => ReportType::GovernanceReview->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5a',
                    'npo_engagement_id' => $engagement->getKey(),
                    'legal_structure' => $engagement->legal_structure?->value,
                    'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
                    'reviewed_finding_ids' => $findings->pluck('id')->values()->all(),
                    's42g_statement_required' => true,
                    'legal_disclaimer_required' => true,
                    'redactions' => [],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->governanceReviewSections($engagement, $findings, $actor) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.governance_review_report_generated', subject: $report, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'sections' => $report->sections()->count(),
                'reviewed_findings' => $findings->count(),
                'pdf_path' => $report->pdf_path,
            ]);

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    public function composeFunderAccountability(NpoEngagement $engagement, ?ClientFunderRecord $record = null, ?User $actor = null): Report
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

        return DB::transaction(function () use ($client, $engagement, $record, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::FunderAccountability,
                'title' => ReportType::FunderAccountability->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'client_funder_record_id' => $record->getKey(),
                    'funder_id' => $record->funder_id,
                    'advisor_review_required' => true,
                    'redactions' => [],
                ],
                'review_status' => 'pending_review',
            ]);

            foreach ($this->funderAccountabilitySections($engagement, $record, $actor) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.funder_accountability_report_generated', subject: $report, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'client_funder_record_id' => $record->getKey(),
                'review_status' => 'pending_review',
            ]);

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function composeImpactSummary(NpoEngagement $engagement, array $input, ?User $actor = null): Report
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Impact Summary reports require an NPO engagement with a client.');
        }

        $metrics = (array) ($input['metrics'] ?? []);
        $platformMetrics = (array) ($input['platform_metrics'] ?? []);
        foreach ($metrics as $key => $value) {
            if (is_numeric($value) && is_numeric($platformMetrics[$key] ?? null) && (float) $value > (float) $platformMetrics[$key]) {
                throw new InvalidArgumentException("Impact metric [{$key}] exceeds recorded platform data.");
            }
        }

        return DB::transaction(function () use ($client, $engagement, $input, $metrics, $platformMetrics, $actor): Report {
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
                    'auto_release_at' => now()->addHours((int) config('npo.impact_summary_auto_release_hours', 48))->toIso8601String(),
                    'platform_metrics' => $platformMetrics,
                    'redactions' => ['fsa_ip'],
                ],
                'review_status' => 'pending_review',
            ]);

            foreach ($this->impactSummarySections($engagement, $input, $metrics) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.impact_summary_report_generated', subject: $report, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'client_authored' => true,
                'auto_release_at' => $report->metadata['auto_release_at'] ?? null,
            ]);

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    public function markReviewed(Report $report, User $actor): Report
    {
        $report = $report->refresh();

        if (! in_array($report->type, [ReportType::Trajectory, ReportType::FunderAccountability, ReportType::ImpactSummary], true)) {
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
        $sections = [
            $this->valuationSection($client, $waterfall, $valuation),
        ];

        $findings
            ->reject(fn (AnalysisFinding $finding): bool => $finding->lens === AnalysisLens::Prescriptive)
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
            $this->trajectoryNarrativeSection($client, $snapshots, $valuations, $findings),
        ];
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array<int, array<string, mixed>>
     */
    private function dueDiligenceSections(
        DdEngagement $engagement,
        Collection $findings,
        ?DdValuation $valuation,
        Collection $risks,
        Collection $integrationPlan,
        array $recommendation,
    ): array {
        return [
            $this->ddExecutiveSummarySection($engagement, $findings, $valuation, $risks, $recommendation),
            $this->ddValuationSection($engagement, $valuation),
            $this->ddWorkstreamFindingsSection($engagement, $findings),
            $this->ddRiskRegisterSection($engagement, $risks),
            $this->ddPriceAdjustmentSection($engagement, $risks),
            $this->ddIntegrationPlanSection($engagement, $integrationPlan),
            $this->ddBuyerReadinessSection($engagement, $valuation, $risks),
            $this->ddRecommendationSection($engagement, $recommendation),
            $this->ddLiabilityDisclaimerSection($engagement),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     * @return array<int, array<string, mixed>>
     */
    private function entrepreneurAssessmentSections(
        PlanAssessment $assessment,
        Collection $criteria,
        float $weightedScore,
        string $overallGrade,
        PvCalculation $conceptPv,
    ): array {
        return [
            $this->entrepreneurScoreSection($assessment, $criteria),
            $this->entrepreneurFeedbackSection($assessment, $criteria),
            $this->entrepreneurGradeSection($assessment, $weightedScore, $overallGrade, $conceptPv),
            $this->entrepreneurActionsSection($assessment, $criteria),
        ];
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function governanceReviewSections(NpoEngagement $engagement, Collection $findings, ?User $actor): array
    {
        return [
            $this->governanceExecutiveSummarySection($engagement, $findings),
            $this->governanceS42gStatementSection($engagement, $findings, $actor),
            $this->governanceFindingSection(
                engagement: $engagement,
                findings: $findings,
                keys: ['board_composition'],
                sectionKey: 'board_composition_skills',
                title: 'Board composition and skills assessment',
                fallback: 'No advisor-reviewed board composition finding is available yet.',
            ),
            $this->governanceFindingSection(
                engagement: $engagement,
                findings: $findings,
                keys: ['constitution_currency', 'legal_structure_compliance'],
                sectionKey: 'constitution_currency',
                title: 'Constitution and statutory currency',
                fallback: 'No advisor-reviewed constitution currency finding is available yet.',
            ),
            $this->governanceFindingSection(
                engagement: $engagement,
                findings: $findings,
                keys: ['conflicts_of_interest'],
                sectionKey: 'conflicts_of_interest',
                title: 'Conflicts of interest framework',
                fallback: 'No advisor-reviewed conflicts-of-interest finding is available yet.',
            ),
            $this->governanceFindingSection(
                engagement: $engagement,
                findings: $findings,
                keys: ['financial_oversight'],
                sectionKey: 'financial_oversight',
                title: 'Financial oversight',
                fallback: 'No advisor-reviewed financial oversight finding is available yet.',
            ),
            $this->governanceComplianceStatusSection($engagement, $findings),
            $this->governanceActionPlanSection($engagement, $findings),
            $this->governanceLegalDisclaimerSection($engagement),
        ];
    }

    /**
     * @return Collection<int, GovernanceReviewFinding>
     */
    private function governanceFindings(NpoEngagement $engagement): Collection
    {
        return GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('status', GovernanceReviewFinding::STATUS_REVIEWED)
            ->get()
            ->sortBy(fn (GovernanceReviewFinding $finding): string => sprintf(
                '%02d-%s-%s',
                $this->governanceSeverityPosition($finding->severity),
                $finding->category,
                $finding->finding_key,
            ))
            ->values();
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<string, mixed>
     */
    private function governanceExecutiveSummarySection(NpoEngagement $engagement, Collection $findings): array
    {
        $criticalOrHigh = $findings->filter(
            fn (GovernanceReviewFinding $finding): bool => in_array($finding->severity, [FindingSeverity::Critical, FindingSeverity::High], true)
        );
        $body = sprintf(
            "Governance Review for %s.\nLegal structure: %s.\nAdvisor-reviewed findings: %d.\nPriority governance risks: %d.\n\n%s",
            $engagement->client?->legal_name ?? 'NPO client',
            $engagement->legal_structure?->label() ?? 'not recorded',
            $findings->count(),
            $criticalOrHigh->count(),
            $criticalOrHigh->isEmpty()
                ? 'No critical or high governance findings are currently marked for the board-ready report.'
                : 'Priority findings: '.$criticalOrHigh->pluck('title')->take(5)->implode('; '),
        );

        return $this->governanceSection(
            key: 'executive_summary',
            title: 'Executive summary',
            body: $body,
            findings: $findings,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<string, mixed>
     */
    private function governanceS42gStatementSection(NpoEngagement $engagement, Collection $findings, ?User $actor): array
    {
        $client = $engagement->client;
        $advisor = $actor instanceof User
            ? trim($actor->name.' <'.$actor->email.'>')
            : 'Future Shift Advisory advisor';
        $legalStructure = $engagement->legal_structure?->label() ?? 'not recorded';
        $s42gApplicability = str_contains(strtolower($legalStructure), 'charity')
            ? 's.42G officer eligibility and governance evidence are in scope for this registered-charity review.'
            : 's.42G is recorded as a charity-specific governance lens; applicability should be confirmed against the organisation registration status.';

        $body = sprintf(
            "s.42G evidence statement date: %s.\nScope: Governance Review Report for %s, engagement %s.\nAdvisor: %s.\nLegal structure reviewed: %s.\nEvidence base: %d advisor-reviewed governance finding(s), source attributions, questionnaire evidence, and registry/compliance status where available.\n%s",
            now()->toDateString(),
            $client?->legal_name ?? 'NPO client',
            $engagement->getKey(),
            $advisor,
            $legalStructure,
            $findings->count(),
            $s42gApplicability,
        );

        return $this->governanceSection(
            key: 's42g_evidence_statement',
            title: 's.42G evidence statement',
            body: $body,
            findings: $findings->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, ['legal_structure_compliance', 'constitution_currency'], true)),
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
            metadata: ['mandatory' => true],
        );
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function governanceFindingSection(
        NpoEngagement $engagement,
        Collection $findings,
        array $keys,
        string $sectionKey,
        string $title,
        string $fallback,
    ): array {
        $selected = $findings->filter(
            fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, $keys, true)
        )->values();
        $body = $selected->isEmpty()
            ? $fallback
            : $selected
                ->map(fn (GovernanceReviewFinding $finding): string => sprintf(
                    '%s [%s]: %s',
                    $finding->title,
                    $finding->severity->value,
                    $finding->body,
                ))
                ->implode("\n\n");

        return $this->governanceSection(
            key: $sectionKey,
            title: $title,
            body: $body,
            findings: $selected,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<string, mixed>
     */
    private function governanceComplianceStatusSection(NpoEngagement $engagement, Collection $findings): array
    {
        $selected = $findings->filter(
            fn (GovernanceReviewFinding $finding): bool => in_array($finding->finding_key, [
                'legal_structure_compliance',
                'constitution_currency',
                'paid_staff_holidays_act',
                'unregistered_structure_governance',
            ], true)
        )->values();
        $body = $selected->isEmpty()
            ? 'No advisor-reviewed legal-structure compliance finding is available yet.'
            : $selected
                ->map(fn (GovernanceReviewFinding $finding): string => sprintf(
                    '%s [%s]: %s',
                    $finding->title,
                    $finding->severity->value,
                    $finding->body,
                ))
                ->implode("\n\n");

        return $this->governanceSection(
            key: 'compliance_status',
            title: 'Compliance status by relevant legislation',
            body: $body,
            findings: $selected,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<string, mixed>
     */
    private function governanceActionPlanSection(NpoEngagement $engagement, Collection $findings): array
    {
        $priorities = $findings
            ->reject(fn (GovernanceReviewFinding $finding): bool => $finding->finding_key === 'evidence_depth')
            ->take(6)
            ->values();
        $body = $priorities->isEmpty()
            ? '12-month governance action plan is pending advisor-reviewed findings.'
            : $priorities
                ->map(function (GovernanceReviewFinding $finding, int $index): string {
                    $window = match ($index) {
                        0 => '0-30 days',
                        1, 2 => '31-90 days',
                        3, 4 => '3-6 months',
                        default => '6-12 months',
                    };

                    return sprintf('%s: %s - %s', $window, $finding->title, $this->firstSentence($finding->body));
                })
                ->implode("\n");

        return $this->governanceSection(
            key: 'twelve_month_action_plan',
            title: '12-month governance action plan',
            body: $body,
            findings: $priorities,
            sourceReference: 'npo_engagement:'.$engagement->getKey(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function governanceLegalDisclaimerSection(NpoEngagement $engagement): array
    {
        return $this->governanceSection(
            key: 'legal_disclaimer',
            title: 'Legal disclaimer',
            body: 'This Governance Review Report is prepared for governance discussion and decision support only. It is advisory in nature and is not legal advice, a legal opinion, or a substitute for independent legal advice on the Incorporated Societies Act 2022, Charities Act 2005, Charities Amendment Act 2023, trust law, employment law, tax, funding, or any other statutory obligation.',
            findings: collect(),
            sourceReference: 'npo_governance_disclaimer:'.$engagement->getKey(),
            metadata: ['mandatory' => true],
        );
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<string, mixed>
     */
    private function governanceSection(
        string $key,
        string $title,
        string $body,
        Collection $findings,
        string $sourceReference,
        array $metadata = [],
    ): array {
        $documentSupport = $this->governanceDocumentSupport($findings);

        return [
            'key' => $key,
            'title' => $title,
            'body' => $body,
            'lens' => null,
            'attributions' => $this->governanceAttributions($findings, $title, $sourceReference),
            'document_support' => $documentSupport,
            'document_support_note' => $this->documentSupportNote($documentSupport),
            'data_quality_note' => $this->governanceDataQualityNote($findings),
            'metadata' => [
                ...$metadata,
                'governance_finding_ids' => $findings->pluck('id')->values()->all(),
                'uncertainty' => $findings->pluck('uncertainty.value')->filter()->unique()->values()->all(),
            ],
        ];
    }

    private function governanceSeverityPosition(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::Critical => 1,
            FindingSeverity::High => 2,
            FindingSeverity::Medium => 3,
            FindingSeverity::Low => 4,
            FindingSeverity::Info => 5,
        };
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     */
    private function governanceDocumentSupport(Collection $findings): string
    {
        $documentIds = $findings
            ->flatMap(fn (GovernanceReviewFinding $finding): array => $this->governanceDocumentIds($finding->evidence ?? []))
            ->filter()
            ->unique()
            ->values();

        if ($documentIds->isEmpty()) {
            return AnalysisFinding::DOCUMENT_SUPPORT_NONE;
        }

        $verifications = DocumentVerification::query()
            ->whereIn('document_id', $documentIds->all())
            ->get();

        if ($verifications->contains('outcome', DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY)) {
            return AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY;
        }

        if ($verifications->contains('outcome', DocumentVerification::OUTCOME_ADVISORY_FLAG)) {
            return AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG;
        }

        if ($verifications->contains('outcome', DocumentVerification::OUTCOME_VERIFIED)) {
            return AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED;
        }

        return AnalysisFinding::DOCUMENT_SUPPORT_NONE;
    }

    /**
     * @return array<int, string>
     */
    private function governanceDocumentIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        $documentIds = $value['attached_document_ids'] ?? null;
        if (is_array($documentIds)) {
            foreach ($documentIds as $documentId) {
                if (is_scalar($documentId) && trim((string) $documentId) !== '') {
                    $ids[] = trim((string) $documentId);
                }
            }
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $ids = [...$ids, ...$this->governanceDocumentIds($child)];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function governanceAttributions(Collection $findings, string $title, string $sourceReference): array
    {
        $attributions = $findings
            ->flatMap(fn (GovernanceReviewFinding $finding): array => is_array($finding->attributions) ? $finding->attributions : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'claim' => (string) ($item['claim'] ?? $title),
                'source_reference' => (string) ($item['source_reference'] ?? ''),
            ])
            ->filter(fn (array $item): bool => trim($item['claim']) !== '' && trim($item['source_reference']) !== '')
            ->values();

        if ($attributions->isEmpty()) {
            return [[
                'claim' => $title,
                'source_reference' => $sourceReference,
            ]];
        }

        return $attributions
            ->unique(fn (array $item): string => $item['claim'].'|'.$item['source_reference'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, GovernanceReviewFinding>  $findings
     */
    private function governanceDataQualityNote(Collection $findings): string
    {
        if ($findings->isEmpty()) {
            return 'Data quality note: section is mandatory report text and should be read with the advisor-reviewed governance evidence pack.';
        }

        $uncertainties = $findings
            ->map(fn (GovernanceReviewFinding $finding): string => $finding->uncertainty->value)
            ->unique()
            ->values()
            ->implode(', ');

        return sprintf(
            'Data quality note: based on %d advisor-reviewed, source-attributed governance finding(s). Recorded uncertainty: %s.',
            $findings->count(),
            $uncertainties !== '' ? $uncertainties : 'not recorded',
        );
    }

    private function firstSentence(string $body): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/', trim($body), 2);
        $sentence = is_array($parts) && isset($parts[0]) ? $parts[0] : $body;

        return trim($sentence);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function entrepreneurCriteriaRows(PlanAssessment $assessment): Collection
    {
        $framework = $assessment->ratingFramework;
        $aiScores = collect($assessment->ai_scores ?? [])->keyBy(fn (array $score): int => (int) ($score['criterion_number'] ?? 0));
        $advisorScores = collect($assessment->advisor_scores ?? [])->keyBy(fn (array $score): int => (int) ($score['criterion_number'] ?? 0));
        $documentSupport = $this->entrepreneurDocumentSupport($assessment);

        return $framework->criteria
            ->map(function ($criterion) use ($aiScores, $advisorScores, $framework, $documentSupport): array {
                $ai = $aiScores->get($criterion->number, []);
                $advisor = $advisorScores->get($criterion->number);
                $hasAdvisorScore = is_array($advisor) && is_numeric($advisor['score'] ?? null);
                $score = $hasAdvisorScore ? (int) $advisor['score'] : (int) ($ai['score'] ?? 0);
                $score = max(0, min(100, $score));

                return [
                    'criterion_id' => (string) $criterion->getKey(),
                    'criterion_number' => $criterion->number,
                    'criterion_name' => $criterion->name,
                    'weight' => (float) $criterion->weight,
                    'ai_score' => is_numeric($ai['score'] ?? null) ? (int) $ai['score'] : null,
                    'advisor_score' => $hasAdvisorScore ? (int) $advisor['score'] : null,
                    'score' => $score,
                    'grade' => $framework instanceof RatingFramework ? $framework->gradeFor($score) : 'needs_work',
                    'rationale' => $hasAdvisorScore
                        ? 'Advisor adjustment: '.(string) ($advisor['note'] ?? 'No note recorded.')
                        : (string) ($ai['rationale'] ?? 'First-pass assessment did not provide a rationale.'),
                    'attributions' => $this->normalisedEntrepreneurAttributions((array) ($ai['attributions'] ?? []), (string) $criterion->getKey()),
                    'document_support' => $documentSupport['support'],
                    'document_support_note' => $documentSupport['note'],
                    'data_quality_indicator' => $documentSupport['data_quality_indicator'],
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     */
    private function entrepreneurScoreSection(PlanAssessment $assessment, Collection $criteria): array
    {
        $documentSupport = $this->entrepreneurDocumentSupport($assessment);
        $body = $criteria
            ->map(function (array $row): string {
                $advisorNotation = $row['advisor_score'] === null
                    ? 'advisor adjustment: none'
                    : 'advisor adjustment: '.$row['advisor_score'].'/100';

                return sprintf(
                    '%02d. %s - final %d/100 (%s); AI first-pass %s; %s; document support: %s; data quality: %s.',
                    $row['criterion_number'],
                    $row['criterion_name'],
                    $row['score'],
                    $this->gradeLabel((string) $row['grade']),
                    $row['ai_score'] === null ? 'n/a' : $row['ai_score'].'/100',
                    $advisorNotation,
                    $row['document_support_note'],
                    $row['data_quality_indicator'],
                );
            })
            ->implode("\n");

        return $this->generatedSection(
            key: 'entrepreneur_criterion_scores',
            title: 'Criterion scores and evidence notation',
            body: $body,
            sourceReference: 'plan_assessment:'.$assessment->getKey(),
            documentSupport: $documentSupport['support'],
            dataQualityNote: 'Data quality note: scores combine AI first-pass scoring, advisor adjustments where present, section document-support notation, and current draft-plan evidence.',
            metadata: [
                'criteria' => $criteria->values()->all(),
                'assessment_round' => $assessment->round,
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     */
    private function entrepreneurFeedbackSection(PlanAssessment $assessment, Collection $criteria): array
    {
        $body = $criteria
            ->map(fn (array $row): string => sprintf(
                '%02d. %s: %s',
                $row['criterion_number'],
                $row['criterion_name'],
                $row['rationale'],
            ))
            ->implode("\n");

        return $this->generatedSection(
            key: 'entrepreneur_criterion_feedback',
            title: 'Written feedback by criterion',
            body: $body,
            sourceReference: 'plan_assessment_feedback:'.$assessment->getKey(),
            documentSupport: $this->entrepreneurDocumentSupport($assessment)['support'],
            dataQualityNote: 'Data quality note: criterion feedback is intentionally direct and may identify gaps even where the draft is promising.',
            metadata: [
                'feedback_count' => $criteria->count(),
            ],
        );
    }

    private function entrepreneurGradeSection(
        PlanAssessment $assessment,
        float $weightedScore,
        string $overallGrade,
        PvCalculation $conceptPv,
    ): array {
        $gradeLabel = $this->gradeLabel($overallGrade);
        $presentValue = (float) data_get($conceptPv->result, 'present_value', 0);
        $readiness = match ($overallGrade) {
            'exceptional' => 'The plan is ready for focused advisor-supported execution, subject to normal commercial validation.',
            'strong' => 'The plan is close to ready; resolve the listed evidence gaps before relying on it for launch decisions.',
            'developing' => 'The plan is directionally useful but not ready for launch without material revision and evidence.',
            default => 'This plan is not ready for launch or advisory conversion yet; it needs clearer proof before commitment.',
        };
        $body = sprintf(
            "Overall grade: %s (%0.2f/100 weighted).\nRationale: %s\nConcept PV projection: NZD %s present value using draft-stage cash-flow assumptions and a risk-adjusted discount rate of %0.1f%%.",
            $gradeLabel,
            $weightedScore,
            $readiness,
            number_format($presentValue, 0),
            ((float) $conceptPv->discount_rate) * 100,
        );

        return $this->generatedSection(
            key: 'entrepreneur_overall_grade',
            title: 'Overall grade and concept PV',
            body: $body,
            sourceReference: 'plan_assessment_grade:'.$assessment->getKey(),
            documentSupport: $this->entrepreneurDocumentSupport($assessment)['support'],
            dataQualityNote: 'Data quality note: concept PV is a projection from plan maturity, not a valuation or investment recommendation.',
            metadata: [
                'overall_grade' => $overallGrade,
                'overall_grade_label' => $gradeLabel,
                'weighted_score' => $weightedScore,
                'concept_pv_calculation_id' => $conceptPv->getKey(),
                'concept_pv_present_value' => $presentValue,
                'discount_rate' => $conceptPv->discount_rate,
                'discount_method' => $conceptPv->discount_method?->value,
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     */
    private function entrepreneurActionsSection(PlanAssessment $assessment, Collection $criteria): array
    {
        $priorityRows = $criteria
            ->sortBy('score')
            ->take(4)
            ->values();
        $gapTags = $priorityRows
            ->flatMap(fn (array $row): array => $this->criterionGapTags((string) $row['criterion_name']))
            ->unique()
            ->values()
            ->all();
        $resources = $this->entrepreneurResources($gapTags);
        $resourceLines = $resources->isEmpty()
            ? ['NZ resources: no matching active resources found for these gaps.']
            : $resources
                ->map(fn (NzResource $resource): string => sprintf('NZ resource: %s (%s)', $resource->title, $resource->url))
                ->all();
        $actions = $priorityRows
            ->map(function (array $row, int $index) use ($resources): string {
                $resource = $resources->get($index % max(1, $resources->count()));
                $resourceText = $resource instanceof NzResource ? ' Use '.$resource->title.'.' : '';

                return sprintf(
                    '%d. Strengthen %s (current %d/100): add specific evidence, owner actions, dates, and decision criteria before treating this as launch-ready.%s',
                    $index + 1,
                    $row['criterion_name'],
                    $row['score'],
                    $resourceText,
                );
            })
            ->all();

        return $this->generatedSection(
            key: 'entrepreneur_improvement_actions',
            title: 'Prioritised improvement actions',
            body: implode("\n", [...$actions, ...$resourceLines]),
            sourceReference: 'plan_assessment_actions:'.$assessment->getKey(),
            documentSupport: $this->entrepreneurDocumentSupport($assessment)['support'],
            dataQualityNote: 'Data quality note: actions prioritise the lowest scoring criteria and cite NZ resource matches where available.',
            metadata: [
                'prioritised_criteria' => $priorityRows->pluck('criterion_number')->all(),
                'gap_tags' => $gapTags,
                'resources' => $resources->map(fn (NzResource $resource): array => [
                    'id' => $resource->getKey(),
                    'title' => $resource->title,
                    'url' => $resource->url,
                    'gap_tags' => $resource->gap_tags,
                ])->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     */
    private function weightedEntrepreneurScore(RatingFramework $framework, Collection $criteria): float
    {
        return round($criteria->sum(fn (array $row): float => ((float) $row['score']) * (((float) $row['weight']) / 100)), 2);
    }

    private function createEntrepreneurConceptPv(
        PlanAssessment $assessment,
        float $weightedScore,
        string $overallGrade,
        ?User $actor,
    ): PvCalculation {
        $assessment->loadMissing('businessPlan.entrepreneurProfile');
        $plan = $assessment->businessPlan;
        $discountRate = match ($overallGrade) {
            'exceptional' => 0.14,
            'strong' => 0.16,
            'developing' => 0.21,
            default => 0.28,
        };
        $annualOpportunity = round(max(0.0, ($weightedScore - 45.0) * 2400.0), 2);
        $cashFlows = [
            1 => round($annualOpportunity * 0.45, 2),
            2 => round($annualOpportunity * 0.75, 2),
            3 => round($annualOpportunity, 2),
            4 => round($annualOpportunity * 1.12, 2),
            5 => round($annualOpportunity * 1.22, 2),
        ];
        $discounted = $this->discountedRows($cashFlows, $discountRate);
        $presentValue = round(collect($discounted)->sum('present_value'), 2);

        return PvCalculation::query()->create([
            'client_id' => $plan?->client_id,
            'entrepreneur_profile_id' => $plan?->entrepreneur_profile_id,
            'type' => PvType::EntrepreneurConceptProjection,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => $discountRate,
            'discount_rate_rationale' => 'Draft-stage concept PV uses a conservative risk-adjusted rate based on the assessment grade.',
            'inputs' => [
                'assessment_weighted_score' => $weightedScore,
                'overall_grade' => $overallGrade,
                'cash_flows' => collect($cashFlows)->map(fn (float $amount, int $period): array => [
                    'period' => $period,
                    'amount' => $amount,
                ])->values()->all(),
                'method_note' => 'Indicative concept projection from plan maturity; not a valuation.',
            ],
            'result' => [
                'present_value' => $presentValue,
                'discounted_cash_flows' => $discounted,
                'data_quality_indicator' => 'draft_projection',
            ],
            'as_at' => now(),
            'created_by_user_id' => $actor?->getKey(),
            'source_attributions' => [
                [
                    'claim' => 'Concept PV derived from entrepreneur assessment weighted score.',
                    'source_reference' => 'plan_assessment:'.$assessment->getKey(),
                ],
                [
                    'claim' => 'Concept PV derived from current business plan draft.',
                    'source_reference' => 'business_plan:'.$assessment->business_plan_id,
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, float>  $cashFlows
     * @return array<int, array{period:int,amount:float,present_value:float}>
     */
    private function discountedRows(array $cashFlows, float $discountRate): array
    {
        return collect($cashFlows)
            ->map(fn (float $amount, int $period): array => [
                'period' => $period,
                'amount' => $amount,
                'present_value' => round($amount / ((1 + $discountRate) ** $period), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{support:string,note:string,data_quality_indicator:string}
     */
    private function entrepreneurDocumentSupport(PlanAssessment $assessment): array
    {
        $count = (int) data_get($assessment->document_support, 'attached_document_count', 0);
        $support = $count > 0
            ? AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED
            : AnalysisFinding::DOCUMENT_SUPPORT_NONE;

        return [
            'support' => $support,
            'note' => $count > 0
                ? "verified document evidence is linked ({$count} attachment(s))."
                : 'no verified document support recorded for the scored sections.',
            'data_quality_indicator' => $count > 0 ? 'document-supported draft' : 'draft-only evidence',
        ];
    }

    /**
     * @param  array<int, mixed>  $attributions
     * @return array<int, array{claim:string,source_reference:string}>
     */
    private function normalisedEntrepreneurAttributions(array $attributions, string $criterionId): array
    {
        $normalised = collect($attributions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'claim' => (string) ($item['claim'] ?? 'Criterion assessment source.'),
                'source_reference' => (string) ($item['source_reference'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['source_reference'] !== '')
            ->values()
            ->all();

        if ($normalised !== []) {
            return $normalised;
        }

        return [[
            'claim' => 'Criterion assessment source.',
            'source_reference' => 'rating_criterion:'.$criterionId,
        ]];
    }

    /**
     * @param  array<int, string>  $gapTags
     * @return Collection<int, NzResource>
     */
    private function entrepreneurResources(array $gapTags): Collection
    {
        return NzResource::query()
            ->where('active', true)
            ->get()
            ->filter(fn (NzResource $resource): bool => array_intersect($resource->gap_tags ?? [], $gapTags) !== [])
            ->take(4)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function criterionGapTags(string $criterionName): array
    {
        $name = strtolower($criterionName);

        if (str_contains($name, 'legal') || str_contains($name, 'intellectual') || str_contains($name, 'means')) {
            return ['legal'];
        }

        if (str_contains($name, 'industry') || str_contains($name, 'location') || str_contains($name, 'apart')) {
            return ['market', 'demand'];
        }

        if (str_contains($name, 'goal') || str_contains($name, 'mission') || str_contains($name, 'vision') || str_contains($name, 'culture')) {
            return ['strategy', 'foundation'];
        }

        return ['foundation'];
    }

    private function gradeLabel(string $grade): string
    {
        return match ($grade) {
            'exceptional' => 'Exceptional',
            'strong' => 'Strong',
            'developing' => 'Developing',
            default => 'Needs Work',
        };
    }

    /**
     * @return Collection<int, AnalysisFinding>
     */
    private function ddFindings(DdEngagement $engagement): Collection
    {
        $workstreams = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->with('analysisRun.findings')
            ->get();

        return $workstreams
            ->flatMap(fn (DdWorkstream $workstream): Collection => $workstream->analysisRun?->findings ?? collect())
            ->filter(fn (mixed $finding): bool => $finding instanceof AnalysisFinding)
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return Collection<int, DdRiskRegisterItem>
     */
    private function refreshDdRiskRegister(DdEngagement $engagement, Collection $findings, ?DdValuation $valuation): Collection
    {
        DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->delete();

        if ($findings->isEmpty()) {
            return collect();
        }

        $workstreamsByRun = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNotNull('analysis_run_id')
            ->pluck('workstream', 'analysis_run_id');
        $baseValue = $this->ddValuationMidpoint($valuation) ?: 100000.0;

        $riskInputs = $findings
            ->map(fn (AnalysisFinding $finding): array => [
                'analysis_finding_id' => $finding->getKey(),
                'title' => $finding->title,
                'financial_impact' => $this->severityImpact($finding->severity, $baseValue),
                'probability' => $this->severityProbability($finding->severity),
                'duration_years' => 1,
                'source_reference' => 'analysis_finding:'.$finding->getKey(),
            ])
            ->all();

        $riskCosts = collect($this->riskCosts->rank($engagement->client, $riskInputs));

        return $riskCosts
            ->map(function (RiskCost $riskCost) use ($engagement, $findings, $workstreamsByRun): DdRiskRegisterItem {
                /** @var AnalysisFinding|null $finding */
                $finding = $findings->firstWhere('id', $riskCost->analysis_finding_id);
                $riskLevel = $this->riskLevel($finding?->severity ?? FindingSeverity::Info);

                return DdRiskRegisterItem::query()->create([
                    'client_id' => $engagement->client_id,
                    'dd_engagement_id' => $engagement->getKey(),
                    'analysis_finding_id' => $finding?->getKey(),
                    'risk_cost_id' => $riskCost->getKey(),
                    'risk_level' => $riskLevel,
                    'category' => (string) ($finding === null ? 'general' : ($workstreamsByRun[$finding->analysis_run_id] ?? 'general')),
                    'title' => $riskCost->title,
                    'body' => $finding?->body ?? $riskCost->title,
                    'financial_impact' => $riskCost->financial_impact,
                    'probability' => $riskCost->probability,
                    'pv_of_cost' => $riskCost->pv_of_cost,
                    'price_adjustment_nzd' => $this->priceAdjustment($riskLevel, $riskCost->pv_of_cost),
                    'rank' => $riskCost->rank,
                    'source_attributions' => $riskCost->source_attributions,
                ]);
            })
            ->sortBy('rank')
            ->values();
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return Collection<int, DdIntegrationPlanItem>
     */
    private function refreshDdIntegrationPlan(DdEngagement $engagement, Collection $risks): Collection
    {
        DdIntegrationPlanItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->delete();

        $actions = $risks
            ->take(4)
            ->values()
            ->map(function (DdRiskRegisterItem $risk, int $index) use ($engagement): DdIntegrationPlanItem {
                $day = [1, 30, 60, 90][$index] ?? 90;

                return DdIntegrationPlanItem::query()->create([
                    'client_id' => $engagement->client_id,
                    'dd_engagement_id' => $engagement->getKey(),
                    'dd_risk_register_id' => $risk->getKey(),
                    'day' => $day,
                    'phase' => $day <= 30 ? 'stabilise' : ($day <= 60 ? 'integrate' : 'optimise'),
                    'action' => sprintf('Resolve %s DD risk: %s', str_replace('_', ' ', $risk->risk_level), $risk->title),
                    'owner' => 'advisor',
                    'priority' => in_array($risk->risk_level, [DdRiskRegisterItem::LEVEL_DEAL_KILLER, DdRiskRegisterItem::LEVEL_MAJOR], true) ? 'high' : 'medium',
                    'metadata' => [
                        'risk_level' => $risk->risk_level,
                        'pv_of_cost' => $risk->pv_of_cost,
                    ],
                ]);
            });

        $actions->push(DdIntegrationPlanItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->getKey(),
            'day' => 100,
            'phase' => 'review',
            'action' => 'Complete 100-day integration review against DD findings, price adjustments, and buyer-readiness assumptions.',
            'owner' => 'advisor',
            'priority' => $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_DEAL_KILLER) ? 'high' : 'medium',
            'metadata' => [
                'risk_count' => $risks->count(),
            ],
        ]));

        return $actions->sortBy('day')->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array<string, mixed>
     */
    private function ddExecutiveSummarySection(
        DdEngagement $engagement,
        Collection $findings,
        ?DdValuation $valuation,
        Collection $risks,
        array $recommendation,
    ): array {
        $body = sprintf(
            "Target: %s.\nRecommendation: %s.\nFindings reviewed: %d.\nMaterial DD risks: %d.\nValuation midpoint: %s.\nRationale: %s",
            $engagement->target_name,
            ucfirst(str_replace('_', ' ', $recommendation['recommendation'])),
            $findings->count(),
            $risks->whereIn('risk_level', [DdRiskRegisterItem::LEVEL_DEAL_KILLER, DdRiskRegisterItem::LEVEL_MAJOR])->count(),
            $this->money($this->ddValuationMidpoint($valuation)),
            $recommendation['rationale'],
        );

        return $this->generatedSection(
            key: 'dd_executive_summary',
            title: 'Executive summary',
            body: $body,
            sourceReference: 'dd_engagement:'.$engagement->getKey(),
            documentSupport: $this->strongestDocumentSupport($findings),
            dataQualityNote: 'Data quality note: DD summary is assembled from completed workstream findings, valuation rows, and risk PV rows.',
            metadata: [
                'dd_engagement_id' => $engagement->getKey(),
                'recommendation' => $recommendation['recommendation'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ddValuationSection(DdEngagement $engagement, ?DdValuation $valuation): array
    {
        if (! $valuation instanceof DdValuation) {
            return $this->generatedSection(
                key: 'dd_valuation',
                title: 'Valuation',
                body: 'No DD valuation has been generated yet.',
                sourceReference: 'dd_valuation:none:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: DD valuation is pending.',
            );
        }

        $valuation->loadMissing('businessValuation', 'pvCalculation');
        $businessValuation = $valuation->businessValuation;
        $body = sprintf(
            "SDE method: %s.\nEBITDA method: %s.\nDCF/PV method: %s.\nReconciled NZD range: %s low, %s midpoint, %s high.\nFX: %s to NZD at %s, timestamp %s.\nBuyer position: %s.",
            $this->methodValue($businessValuation?->sde_value),
            $this->methodValue($businessValuation?->ebitda_value),
            $this->methodValue($businessValuation?->dcf_value),
            $this->money(data_get($valuation->normalised_values, 'reconciled.low')),
            $this->money(data_get($valuation->normalised_values, 'reconciled.mid')),
            $this->money(data_get($valuation->normalised_values, 'reconciled.high')),
            $valuation->source_currency,
            number_format($valuation->source_to_nzd_rate, 4),
            $valuation->rate_timestamp?->toDateTimeString() ?? 'n/a',
            str_replace('_', ' ', (string) data_get($valuation->buyer_position, 'position')),
        );

        return $this->generatedSection(
            key: 'dd_valuation',
            title: 'Valuation',
            body: $body,
            sourceReference: 'dd_valuation:'.$valuation->getKey(),
            dataQualityNote: 'Data quality note: DD valuation reuses the persisted business valuation and PV calculation, with FX normalisation where required.',
            metadata: [
                'dd_valuation_id' => $valuation->getKey(),
                'business_valuation_id' => $valuation->business_valuation_id,
                'pv_calculation_id' => $valuation->pv_calculation_id,
                'buyer_position' => $valuation->buyer_position,
                'sensitivity' => $valuation->sensitivity,
            ],
        );
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>
     */
    private function ddWorkstreamFindingsSection(DdEngagement $engagement, Collection $findings): array
    {
        if ($findings->isEmpty()) {
            $body = 'No completed DD workstream findings are available yet.';
        } else {
            $workstreamsByRun = DdWorkstream::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->whereNotNull('analysis_run_id')
                ->pluck('workstream', 'analysis_run_id');
            $body = $findings
                ->map(fn (AnalysisFinding $finding): string => sprintf(
                    '%s - %s: %s',
                    str((string) ($workstreamsByRun[$finding->analysis_run_id] ?? 'general'))->replace('_', ' ')->title(),
                    $finding->title,
                    $finding->body,
                ))
                ->implode("\n\n");
        }

        return $this->generatedSection(
            key: 'dd_workstream_findings',
            title: 'Workstream findings',
            body: $body,
            sourceReference: 'dd_workstreams:'.$engagement->getKey(),
            documentSupport: $this->strongestDocumentSupport($findings),
            dataQualityNote: 'Data quality note: findings come from completed DD workstreams on the shared analysis spine.',
            metadata: [
                'finding_ids' => $findings->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<string, mixed>
     */
    private function ddRiskRegisterSection(DdEngagement $engagement, Collection $risks): array
    {
        $body = $risks->isEmpty()
            ? 'No DD risks have been ranked yet.'
            : $risks
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '#%d %s - %s (%s PV cost)',
                    $risk->rank,
                    str_replace('_', ' ', $risk->risk_level),
                    $risk->title,
                    $this->money($risk->pv_of_cost),
                ))
                ->implode("\n");

        return $this->generatedSection(
            key: 'dd_risk_register',
            title: 'Risk register',
            body: $body,
            sourceReference: 'dd_risk_register:'.$engagement->getKey(),
            dataQualityNote: 'Data quality note: risk PV ranking uses persisted DD findings and the shared risk-cost PV engine.',
            metadata: [
                'risk_register_ids' => $risks->pluck('id')->values()->all(),
                'risk_levels' => $risks->countBy('risk_level')->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<string, mixed>
     */
    private function ddPriceAdjustmentSection(DdEngagement $engagement, Collection $risks): array
    {
        $adjustments = $risks
            ->filter(fn (DdRiskRegisterItem $risk): bool => $risk->price_adjustment_nzd > 0)
            ->values();
        $total = $adjustments->sum('price_adjustment_nzd');
        $body = $adjustments->isEmpty()
            ? 'No price adjustment is indicated by the current DD risk register.'
            : $adjustments
                ->map(fn (DdRiskRegisterItem $risk): string => sprintf(
                    '%s: %s adjustment for %s risk.',
                    $risk->title,
                    $this->money($risk->price_adjustment_nzd),
                    str_replace('_', ' ', $risk->risk_level),
                ))
                ->implode("\n")."\nTotal indicative adjustment: ".$this->money($total).'.';

        return $this->generatedSection(
            key: 'dd_price_adjustment',
            title: 'Price adjustment schedule',
            body: $body,
            sourceReference: 'dd_risk_register:'.$engagement->getKey().':price_adjustment',
            dataQualityNote: 'Data quality note: adjustment schedule is indicative and must be reviewed by qualified legal/accounting advisers before negotiation.',
            metadata: [
                'total_price_adjustment_nzd' => $total,
                'risk_register_ids' => $adjustments->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdIntegrationPlanItem>  $integrationPlan
     * @return array<string, mixed>
     */
    private function ddIntegrationPlanSection(DdEngagement $engagement, Collection $integrationPlan): array
    {
        $body = $integrationPlan
            ->map(fn (DdIntegrationPlanItem $item): string => sprintf(
                'Day %d (%s): %s',
                $item->day,
                $item->phase,
                $item->action,
            ))
            ->implode("\n");

        return $this->generatedSection(
            key: 'dd_integration_plan',
            title: '100-day integration plan',
            body: $body,
            sourceReference: 'dd_integration_plans:'.$engagement->getKey(),
            dataQualityNote: 'Data quality note: integration actions are generated from the ranked DD risk register and require advisor review.',
            metadata: [
                'integration_plan_ids' => $integrationPlan->pluck('id')->values()->all(),
            ],
        );
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<string, mixed>
     */
    private function ddBuyerReadinessSection(DdEngagement $engagement, ?DdValuation $valuation, Collection $risks): array
    {
        $completed = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count();
        $itemCount = $engagement->dataRoomItems()->count();
        $dealKillers = $risks->where('risk_level', DdRiskRegisterItem::LEVEL_DEAL_KILLER)->count();
        $readiness = match (true) {
            $dealKillers > 0 => 'not ready until deal-killer risks are resolved',
            $completed < count(DataRoom::WORKSTREAMS) => 'partially ready; DD workstreams remain incomplete',
            ! $valuation instanceof DdValuation => 'partially ready; valuation is missing',
            default => 'ready for advisor-led acquisition decision review',
        };

        return $this->generatedSection(
            key: 'dd_buyer_readiness',
            title: 'Buyer readiness',
            body: sprintf(
                "Readiness: %s.\nCompleted workstreams: %d of %d.\nData room items reviewed: %d.\nDeal-killer risks: %d.",
                $readiness,
                $completed,
                count(DataRoom::WORKSTREAMS),
                $itemCount,
                $dealKillers,
            ),
            sourceReference: 'dd_buyer_readiness:'.$engagement->getKey(),
            dataQualityNote: 'Data quality note: buyer readiness reflects platform DD completion signals and is not acquisition advice.',
            metadata: [
                'completed_workstreams' => $completed,
                'required_workstreams' => count(DataRoom::WORKSTREAMS),
                'data_room_items' => $itemCount,
                'deal_killer_risks' => $dealKillers,
            ],
        );
    }

    /**
     * @param  array{recommendation:string,rationale:string}  $recommendation
     * @return array<string, mixed>
     */
    private function ddRecommendationSection(DdEngagement $engagement, array $recommendation): array
    {
        return $this->generatedSection(
            key: 'dd_recommendation',
            title: 'Recommendation',
            body: sprintf(
                "Recommendation: %s.\nRationale: %s.",
                ucfirst($recommendation['recommendation']),
                $recommendation['rationale'],
            ),
            sourceReference: 'dd_recommendation:'.$engagement->getKey(),
            dataQualityNote: 'Data quality note: recommendation is generated from DD risk, valuation, and workstream completion signals for advisor review.',
            metadata: $recommendation,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ddLiabilityDisclaimerSection(DdEngagement $engagement): array
    {
        return $this->generatedSection(
            key: 'dd_liability_disclaimer',
            title: 'Liability disclaimer',
            body: DdDisclaimer::STANDARD,
            sourceReference: 'dd_disclaimer:'.$engagement->getKey(),
            dataQualityNote: 'Data quality note: this disclaimer is included on every due diligence output.',
        );
    }

    /**
     * @param  array<string, mixed>  $waterfall
     * @return array<string, mixed>
     */
    private function valuationSection(Client $client, array $waterfall, ?BusinessValuation $valuation): array
    {
        $body = $valuation instanceof BusinessValuation
            ? sprintf(
                'Current valuation range is NZD %s to NZD %s, with reconciled midpoint NZD %s.',
                number_format($valuation->reconciled_low, 0),
                number_format($valuation->reconciled_high, 0),
                number_format($valuation->reconciled_mid, 0),
            )
            : sprintf(
                'Current PV is NZD %s and target PV is NZD %s from the latest platform waterfall.',
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
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
                'The advisor view includes current PV NZD %s, improvements NZD %s, risk mitigation NZD %s, and target PV NZD %s.',
                number_format((float) $waterfall['current_pv'], 0),
                number_format((float) $waterfall['improvement_pv'], 0),
                number_format((float) $waterfall['risk_mitigation_pv'], 0),
                number_format((float) $waterfall['target_pv'], 0),
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
            'body' => $finding->body,
            'lens' => $finding->lens->value,
            'attributions' => $this->attributions($finding),
            'document_support' => $finding->document_support,
            'document_support_note' => $this->documentSupportNote($finding->document_support),
            'data_quality_note' => $finding->data_quality_disclaimer
                ?: 'Data quality note: no additional disclaimer recorded for this finding.',
            'metadata' => [
                'analysis_finding_id' => $finding->getKey(),
                'severity' => $finding->severity->value,
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
            'Latest proposal v%s has suggested midpoint fee NZD %s and ROI ratio %s. Proposal status is %s.',
            $proposal->version,
            number_format($fee?->suggested_mid ?? 0, 0),
            number_format($proposal->roi_ratio, 2),
            $proposal->status->value,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function funderAccountabilitySections(NpoEngagement $engagement, ClientFunderRecord $record, ?User $actor): array
    {
        $snapshot = FinancialSnapshot::query()
            ->where('client_id', $engagement->client_id)
            ->latest('period_end')
            ->first();
        $milestones = Milestone::query()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderBy('due_date')
            ->get();
        $completed = $milestones->where('status', Milestone::STATUS_COMPLETED)->count();
        $total = $milestones->count();
        $prompt = new PromptEnvelope(
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
                'financial_snapshot_id' => $snapshot?->getKey(),
            ],
            sourceReferences: array_values(array_filter([
                'client_funder_record:'.$record->getKey(),
                $snapshot instanceof FinancialSnapshot ? 'financial_snapshot:'.$snapshot->getKey() : null,
                'milestones:'.$engagement->getKey(),
            ])),
        );
        $response = $this->ai->summarise($prompt);

        return [
            $this->generatedSection(
                key: 'financial_acquittal',
                title: 'Financial acquittal',
                body: $snapshot instanceof FinancialSnapshot
                    ? sprintf('Latest accounting snapshot for %s reports revenue of NZD %s and operating expenses of NZD %s.', $snapshot->period_end?->toDateString(), number_format((float) data_get($snapshot->profit_and_loss, 'revenue', 0), 0), number_format((float) data_get($snapshot->profit_and_loss, 'operating_expenses', 0), 0))
                    : 'Connected accounting data is not available yet; advisor review is required before funder release.',
                sourceReference: $snapshot instanceof FinancialSnapshot ? 'financial_snapshot:'.$snapshot->getKey() : 'financial_snapshot:none:'.$engagement->getKey(),
            ),
            $this->generatedSection(
                key: 'milestone_completion',
                title: 'Milestone completion',
                body: $total === 0
                    ? 'No engagement-scoped milestones have been recorded for this funder report.'
                    : "{$completed} of {$total} engagement-scoped milestones are complete.",
                sourceReference: 'milestones:'.$engagement->getKey(),
                metadata: ['milestone_ids' => $milestones->pluck('id')->values()->all()],
            ),
            $this->generatedSection(
                key: 'impact_metrics',
                title: 'Impact metrics',
                body: 'Impact metrics are sourced from client-entered platform records and require advisor review before inclusion in the funder copy.',
                sourceReference: 'impact_metrics:'.$engagement->getKey(),
            ),
            $this->generatedSection(
                key: 'ai_accountability_narrative',
                title: 'Advisor-reviewed accountability narrative',
                body: $response->text,
                sourceReference: 'ai_response:'.$response->promptHash,
                dataQualityNote: 'Data quality note: FSA-generated AI narrative is blocked from funder/client release until advisor review marks the report reviewed.',
                metadata: [
                    'advisor_review_required' => true,
                    'ai_response' => $response->toArray(),
                    'generated_by_user_id' => $actor?->getKey(),
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $metrics
     * @return array<int, array<string, mixed>>
     */
    private function impactSummarySections(NpoEngagement $engagement, array $input, array $metrics): array
    {
        return [
            $this->generatedSection(
                key: 'client_impact_summary',
                title: 'Client-authored impact summary',
                body: (string) ($input['summary'] ?? 'Impact summary pending client narrative.'),
                sourceReference: 'impact_summary:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: client-authored narrative; AI assistance is limited to language support and no FSA IP is included.',
                metadata: ['client_authored' => true, 'fsa_ip' => false],
            ),
            $this->generatedSection(
                key: 'fact_checked_metrics',
                title: 'Fact-checked metrics',
                body: collect($metrics)
                    ->map(fn (mixed $value, string|int $key): string => "{$key}: {$value}")
                    ->implode("\n") ?: 'No impact metrics supplied.',
                sourceReference: 'impact_metrics:'.$engagement->getKey(),
                metadata: ['metrics' => $metrics],
            ),
        ];
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
            'data_quality_note' => $dataQualityNote ?: 'Data quality note: platform-generated section from persisted records.',
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
            AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED => 'Document support: verified document evidence is linked.',
            AnalysisFinding::DOCUMENT_SUPPORT_ADVISORY_FLAG => 'Document support: advisor flag is noted and must be considered.',
            AnalysisFinding::DOCUMENT_SUPPORT_ACCURACY_DISCREPANCY => 'Document support: accuracy discrepancy is noted.',
            default => 'Document support: no verified document support recorded.',
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
            ? 'Data quality note: no additional disclaimer recorded for implementation findings.'
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

    private function latestDdValuation(DdEngagement $engagement): ?DdValuation
    {
        return DdValuation::query()
            ->with('businessValuation', 'pvCalculation')
            ->where('dd_engagement_id', $engagement->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    /**
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array{recommendation:string,rationale:string}
     */
    private function ddRecommendation(Collection $risks, ?DdValuation $valuation): array
    {
        $hasDealKiller = $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_DEAL_KILLER);
        $hasMajor = $risks->contains(fn (DdRiskRegisterItem $risk): bool => $risk->risk_level === DdRiskRegisterItem::LEVEL_MAJOR);
        $buyerPosition = (string) data_get($valuation?->buyer_position, 'position', 'no_valuation');

        if ($hasDealKiller) {
            return [
                'recommendation' => DdEngagement::RECOMMENDATION_ABANDON,
                'rationale' => 'At least one deal-killer DD risk requires abandonment unless resolved outside the platform.',
            ];
        }

        if ($hasMajor || $buyerPosition === 'renegotiate_or_walkaway') {
            return [
                'recommendation' => DdEngagement::RECOMMENDATION_RENEGOTIATE,
                'rationale' => 'Major DD risk or valuation pressure indicates renegotiation before proceeding.',
            ];
        }

        return [
            'recommendation' => DdEngagement::RECOMMENDATION_PROCEED,
            'rationale' => 'No deal-killer or major DD risk is present and valuation signals do not require renegotiation.',
        ];
    }

    private function ddValuationMidpoint(?DdValuation $valuation): ?float
    {
        $mid = data_get($valuation?->normalised_values, 'reconciled.mid');

        return is_numeric($mid) ? (float) $mid : null;
    }

    private function severityImpact(FindingSeverity $severity, float $baseValue): float
    {
        $ratio = match ($severity) {
            FindingSeverity::Critical => 0.30,
            FindingSeverity::High => 0.16,
            FindingSeverity::Medium => 0.08,
            FindingSeverity::Low => 0.03,
            FindingSeverity::Info => 0.01,
        };

        return round(max(10000.0, $baseValue * $ratio), 2);
    }

    private function severityProbability(FindingSeverity $severity): float
    {
        return match ($severity) {
            FindingSeverity::Critical => 0.85,
            FindingSeverity::High => 0.65,
            FindingSeverity::Medium => 0.45,
            FindingSeverity::Low => 0.25,
            FindingSeverity::Info => 0.10,
        };
    }

    private function riskLevel(FindingSeverity $severity): string
    {
        return match ($severity) {
            FindingSeverity::Critical => DdRiskRegisterItem::LEVEL_DEAL_KILLER,
            FindingSeverity::High => DdRiskRegisterItem::LEVEL_MAJOR,
            FindingSeverity::Medium => DdRiskRegisterItem::LEVEL_MINOR,
            FindingSeverity::Low, FindingSeverity::Info => DdRiskRegisterItem::LEVEL_INFORMATIONAL,
        };
    }

    private function priceAdjustment(string $riskLevel, float $pvOfCost): float
    {
        $ratio = match ($riskLevel) {
            DdRiskRegisterItem::LEVEL_DEAL_KILLER => 1.0,
            DdRiskRegisterItem::LEVEL_MAJOR => 0.60,
            DdRiskRegisterItem::LEVEL_MINOR => 0.20,
            default => 0.0,
        };

        return round($pvOfCost * $ratio, 2);
    }

    private function methodValue(mixed $value): string
    {
        if (! is_array($value)) {
            return 'n/a';
        }

        $mid = $value['mid'] ?? data_get($value, 'reconciled.mid') ?? $value['present_value'] ?? null;

        return is_numeric($mid) ? $this->money($mid) : 'n/a';
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? 'NZD '.number_format((float) $value, 0) : 'n/a';
    }

    private function renderAndStorePdf(Report $report): void
    {
        $pdf = $this->renderer->render($this->html($report));
        $path = sprintf(
            'reports/%s/%s/%s-%s.pdf',
            $this->reportSubjectKey($report),
            now()->format('Y/m'),
            Str::uuid(),
            $report->type->value,
        );

        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Report PDF could not be stored.');
        }

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_byte_size' => strlen($pdf),
        ])->save();
    }

    private function renderAndStorePptx(Report $report): void
    {
        $pptx = $this->pptx->render($report);
        $path = sprintf(
            'reports/%s/%s/%s-%s.pptx',
            $this->reportSubjectKey($report),
            now()->format('Y/m'),
            Str::uuid(),
            $report->type->value,
        );

        $written = Storage::disk('secure_local')->put($path, $pptx);

        if ($written !== true) {
            throw new RuntimeException('Report PowerPoint could not be stored.');
        }

        $report->forceFill([
            'pptx_path' => $path,
            'pptx_byte_size' => strlen($pptx),
        ])->save();
    }

    private function html(Report $report): string
    {
        $report->loadMissing(['client', 'entrepreneurProfile', 'sections']);
        $sections = $report->sections
            ->sortBy('position')
            ->map(fn (ReportSection $section): string => $this->sectionHtml($section))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.brand p { margin: 0; }
.section { border: 1px solid #d8e2dc; margin-bottom: 16px; padding: 12px; break-inside: avoid; }
.section h2 { color: #214f44; font-size: 15px; margin: 0 0 6px; }
.body { white-space: pre-wrap; }
.note { color: #4b5563; font-size: 10px; margin-top: 6px; }
.chart { margin-top: 10px; }
</style>
</head>
<body>
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>%s</p>
<p>%s</p>
</header>
%s
</body>
</html>
HTML,
            $this->escape($report->title),
            $this->escape($report->type->label()),
            $this->escape($report->client?->legal_name ?? $report->entrepreneurProfile?->name ?? 'Client'),
            $sections,
        );
    }

    private function reportSubjectKey(Report $report): string
    {
        if (is_string($report->client_id) && $report->client_id !== '') {
            return $report->client_id;
        }

        return 'entrepreneur-'.$report->entrepreneur_profile_id;
    }

    private function sectionHtml(ReportSection $section): string
    {
        $sources = collect($section->attributions ?? [])
            ->map(fn (array $item): string => (string) ($item['source_reference'] ?? 'source'))
            ->filter()
            ->implode(', ');
        $chart = is_string($section->metadata['chart_html'] ?? null)
            ? '<div class="chart">'.$section->metadata['chart_html'].'</div>'
            : '';

        return sprintf(
            <<<'HTML'
<section class="section">
<h2>%s</h2>
<div class="body">%s</div>
%s
<p class="note">%s</p>
<p class="note">%s</p>
<p class="note">Sources: %s</p>
</section>
HTML,
            $this->escape($section->title),
            nl2br($this->escape($section->body)),
            $chart,
            $this->escape($section->document_support_note),
            $this->escape($section->data_quality_note),
            $this->escape($sources),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
