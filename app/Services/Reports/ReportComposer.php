<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AnalysisLens;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\PvType;
use App\Enums\ReportType;
use App\Models\AnalysisFinding;
use App\Models\BusinessPlan;
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
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\NpoValueCalculation;
use App\Models\NzResource;
use App\Models\PlanAssessment;
use App\Models\PlanSection;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\QuestionnaireResponse;
use App\Models\RatingFramework;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\RiskCost;
use App\Models\Template;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdDisclaimer;
use App\Services\Npo\NpoImpactMetricRecorder;
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
        private readonly NpoImpactMetricRecorder $npoImpactMetrics,
        private readonly AcquisitionPlanRequirements $acquisitionPlanRequirements,
        private readonly UploadedReportTemplateRenderer $uploadedTemplates,
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
            $template = $this->activeReportTemplateFor($type);

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
                    'template' => $this->reportTemplateMetadata($template),
                ],
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
                'review_status' => 'pending_review',
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

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('post_acquisition.gap_report_generated', subject: $report, actor: $actor, after: [
                'post_acquisition_migration_id' => $migration->getKey(),
                'dd_engagement_id' => $engagement->getKey(),
                'sections' => $report->sections()->count(),
                'missing_plan_requirements' => $completion['missing'],
                'pdf_path' => $report->pdf_path,
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

    public function composeNpoHealth(NpoEngagement $engagement, ?User $actor = null): Report
    {
        [$client, $scores] = $this->npoReportInputs($engagement);

        return DB::transaction(function () use ($client, $engagement, $scores, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::NpoHealth,
                'title' => ReportType::NpoHealth->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'plain_english' => true,
                    'board_audience' => true,
                    'redactions' => ['advisor_workings'],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->npoHealthSections($engagement, $scores) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.health_report_generated', subject: $report, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'sections' => $report->sections()->count(),
            ]);

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    public function composeNpoAdvisor(NpoEngagement $engagement, ?User $actor = null): Report
    {
        [$client, $scores] = $this->npoReportInputs($engagement);

        return DB::transaction(function () use ($client, $engagement, $scores, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::NpoAdvisor,
                'title' => ReportType::NpoAdvisor->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'confidential' => true,
                    'header_colour' => 'cognac',
                    'redactions' => [],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->npoAdvisorSections($engagement, $scores) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.advisor_report_generated', subject: $report, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'sections' => $report->sections()->count(),
                'confidential' => true,
            ]);

            return $report->refresh()->load(['client', 'npoEngagement', 'sections']);
        });
    }

    public function composeSocialEnterpriseDual(NpoEngagement $engagement, ?User $actor = null): Report
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

        return DB::transaction(function () use ($client, $engagement, $scorecard, $analysis, $actor): Report {
            $report = Report::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $engagement->getKey(),
                'type' => ReportType::SocialEnterpriseDual,
                'title' => ReportType::SocialEnterpriseDual->label().' - '.$client->legal_name,
                'generated_by_user_id' => $actor?->getKey(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => 'phase_5b',
                    'npo_engagement_id' => $engagement->getKey(),
                    'scorecard_id' => $scorecard->getKey(),
                    'tension_analysis_id' => $analysis->getKey(),
                    'redactions' => [],
                ],
                'review_status' => 'not_required',
            ]);

            foreach ($this->socialEnterpriseDualSections($scorecard, $analysis) as $position => $section) {
                ReportSection::query()->create([
                    ...$section,
                    'report_id' => $report->getKey(),
                    'client_id' => $client->getKey(),
                    'position' => $position + 1,
                ]);
            }

            $this->renderAndStorePdf($report->refresh()->load(['client', 'sections']));

            $this->audit->record('npo.social_enterprise_dual_impact_report_generated', subject: $report, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'scorecard_id' => $scorecard->getKey(),
                'tension_analysis_id' => $analysis->getKey(),
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

        $recordedMetrics = $this->npoImpactMetrics->reportMetrics($engagement);
        $metrics = (array) ($input['metrics'] ?? $recordedMetrics['metrics']);
        $platformMetrics = (array) ($input['platform_metrics'] ?? $recordedMetrics['platform_metrics']);
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
        $report->loadMissing(['client', 'entrepreneurProfile', 'sections']);

        $this->renderAndStorePdf($report);

        if ($report->pptx_path !== null) {
            $this->renderAndStorePptx($report->refresh()->load(['client', 'entrepreneurProfile', 'sections']));
        }

        $this->audit->record('report.rerendered', subject: $report, after: [
            'type' => $report->type->value,
            'pdf_path' => $report->pdf_path,
            'pptx_path' => $report->pptx_path,
        ]);

        return $report->refresh();
    }

    public function usesCurrentTemplate(Report $report): bool
    {
        if (! $report->type instanceof ReportType) {
            return true;
        }

        return ($report->metadata['template'] ?? null) === $this->reportTemplateMetadata(
            $this->templateForReport($report),
        );
    }

    private function usesAdvisorReviewGate(Report $report): bool
    {
        if (in_array($report->type, [
            ReportType::DueDiligence,
            ReportType::Trajectory,
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

    private function activeReportTemplateFor(ReportType $type): ?Template
    {
        return Template::query()
            ->usable()
            ->where('category', Template::CATEGORY_REPORT)
            ->get()
            ->filter(fn (Template $template): bool => $this->templateAppliesToReportType($template, $type))
            ->sort(fn (Template $left, Template $right): int => $this->templateSelectionRank($right, $type) <=> $this->templateSelectionRank($left, $type))
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportTemplateMetadata(?Template $template): ?array
    {
        if (! $template instanceof Template) {
            return null;
        }

        $uploadedFile = data_get($template->structure, 'uploaded_file');

        return [
            'id' => $template->getKey(),
            'category' => $template->category,
            'title' => $template->title,
            'version' => $template->version,
            'source_reference' => $template->source_reference,
            'structure_report_type' => data_get($template->structure, 'report_type'),
            'render_strategy' => $this->templateRenderStrategy($template),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'uploaded_file_document_id' => is_array($uploadedFile)
                ? ($uploadedFile['document_id'] ?? null)
                : null,
            'uploaded_file_sha256' => is_array($uploadedFile)
                ? ($uploadedFile['sha256'] ?? null)
                : null,
            'uploaded_file' => is_array($uploadedFile)
                ? ($uploadedFile['original_name'] ?? null)
                : null,
        ];
    }

    private function templateRenderStrategy(Template $template): string
    {
        if ($this->uploadedTemplates->supports($template)) {
            return 'uploaded_docx_html_v3';
        }

        if ($this->isTokenizedHtmlTemplate($template)) {
            return 'tokenized_html_v1';
        }

        return 'branded_html_v1';
    }

    private function templateTitleMatchesType(Template $template, ReportType $type): bool
    {
        return Str::contains(Str::lower($template->title), $this->reportTemplateKeywords($type));
    }

    private function templateAppliesToReportType(Template $template, ReportType $type): bool
    {
        $reportType = data_get($template->structure, 'report_type');

        if (is_string($reportType) && trim($reportType) !== '') {
            return $reportType === $type->value;
        }

        return $this->templateTitleMatchesType($template, $type)
            || $this->isGenericReportTemplate($template);
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:string}
     */
    private function templateSelectionRank(Template $template, ReportType $type): array
    {
        return [
            $this->templateSourceRank($template),
            $this->templateSpecificityRank($template, $type),
            $template->updated_at?->getTimestamp() ?? 0,
            $template->created_at?->getTimestamp() ?? 0,
            $template->version,
            (string) $template->getKey(),
        ];
    }

    private function templateSourceRank(Template $template): int
    {
        if (data_get($template->structure, 'source_kind') === 'uploaded_file'
            || is_array(data_get($template->structure, 'uploaded_file'))) {
            return 2;
        }

        return trim((string) $template->body) !== '' ? 1 : 0;
    }

    private function templateSpecificityRank(Template $template, ReportType $type): int
    {
        $reportType = data_get($template->structure, 'report_type');

        if (is_string($reportType) && $reportType === $type->value) {
            return 2;
        }

        return $this->templateTitleMatchesType($template, $type) ? 1 : 0;
    }

    private function isGenericReportTemplate(Template $template): bool
    {
        $reportType = data_get($template->structure, 'report_type');

        return (! is_string($reportType) || trim($reportType) === '')
            && ! Str::contains(Str::lower($template->title), ['client', 'advisor', 'stakeholder', 'trajectory']);
    }

    /**
     * @return array<int, string>
     */
    private function reportTemplateKeywords(ReportType $type): array
    {
        return match ($type) {
            ReportType::Client => ['client report', 'client'],
            ReportType::Advisor => ['advisor report', 'advisor'],
            ReportType::Stakeholder => ['stakeholder report', 'stakeholder'],
            ReportType::Trajectory => ['business health trajectory report', 'trajectory'],
            default => [Str::lower($type->label()), Str::of($type->value)->replace('_', ' ')->lower()->toString()],
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
            $this->ddPurchasePriceRangeSection($engagement, $valuation, $risks),
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
        $dcfRange = $this->ddValuationRange($valuation, 'dcf_value');
        $reconciledRange = $this->ddValuationRange($valuation, 'reconciled');
        $body = sprintf(
            "Primary DCF/PV value: %s midpoint, with a %s to %s DCF range.\nMarket-multiple cross-checks: SDE %s; EBITDA %s.\nReconciled NZD range: %s low, %s midpoint, %s high.\nFX: %s to NZD at %s, timestamp %s.\nBuyer position: %s.",
            $this->money($dcfRange['mid'] ?? null),
            $this->money($dcfRange['low'] ?? null),
            $this->money($dcfRange['high'] ?? null),
            $this->methodValue($this->ddValuationRange($valuation, 'sde_value')),
            $this->methodValue($this->ddValuationRange($valuation, 'ebitda_value')),
            $this->money($reconciledRange['low'] ?? null),
            $this->money($reconciledRange['mid'] ?? null),
            $this->money($reconciledRange['high'] ?? null),
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
     * @param  Collection<int, DdRiskRegisterItem>  $risks
     * @return array<string, mixed>
     */
    private function ddPurchasePriceRangeSection(DdEngagement $engagement, ?DdValuation $valuation, Collection $risks): array
    {
        if (! $valuation instanceof DdValuation) {
            return $this->generatedSection(
                key: 'dd_purchase_price_range',
                title: 'Estimated purchase-price range',
                body: 'No purchase-price range can be generated until the DD valuation is available.',
                sourceReference: 'dd_purchase_price_range:none:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: purchase-price range is pending valuation inputs.',
            );
        }

        $valuation->loadMissing('businessValuation');
        $dcfRange = $this->ddValuationRange($valuation, 'dcf_value') ?? $this->ddValuationRange($valuation, 'reconciled');
        $marketRange = $this->ddMarketMultipleRange($valuation);
        $precedentRange = $this->ddPrecedentTransactionRange($engagement, $valuation);
        $dealStructureInputs = data_get($engagement->target_details, 'deal_structure_adjustments', [])
            ?: data_get($valuation->buyer_position, 'deal_structure_adjustments', []);
        $synergyInputs = data_get($engagement->target_details, 'synergy_adjustments', [])
            ?: data_get($valuation->buyer_position, 'synergy_adjustments', []);
        $dealStructureAdjustment = $this->ddAdjustmentTotal($dealStructureInputs);
        $synergyAdjustment = $this->ddAdjustmentTotal($synergyInputs);
        $riskAdjustment = round((float) $risks->sum('price_adjustment_nzd'), 2);
        $purchaseRange = $dcfRange === null
            ? null
            : $this->applyPurchasePriceAdjustments($dcfRange, $dealStructureAdjustment, $synergyAdjustment, $riskAdjustment);

        $body = sprintf(
            "Primary basis: Discounted Cash Flow (DCF), %s low, %s midpoint, %s high.\nCross-checks: market multiples indicate %s low, %s midpoint, %s high; precedent transactions indicate %s low, %s midpoint, %s high.\nAdjustments applied to the DCF range: deal structure %s, synergies %s, due-diligence risk %s.\nEstimated purchase-price range for advisor review: %s low, %s midpoint, %s high.",
            $this->money($dcfRange['low'] ?? null),
            $this->money($dcfRange['mid'] ?? null),
            $this->money($dcfRange['high'] ?? null),
            $this->money($marketRange['low'] ?? null),
            $this->money($marketRange['mid'] ?? null),
            $this->money($marketRange['high'] ?? null),
            $this->money($precedentRange['low'] ?? null),
            $this->money($precedentRange['mid'] ?? null),
            $this->money($precedentRange['high'] ?? null),
            $this->money($dealStructureAdjustment),
            $this->money($synergyAdjustment),
            $this->money($riskAdjustment),
            $this->money($purchaseRange['low'] ?? null),
            $this->money($purchaseRange['mid'] ?? null),
            $this->money($purchaseRange['high'] ?? null),
        );

        return $this->generatedSection(
            key: 'dd_purchase_price_range',
            title: 'Estimated purchase-price range',
            body: $body,
            sourceReference: 'dd_purchase_price_range:'.$valuation->getKey(),
            dataQualityNote: 'Data quality note: range is advisor-facing and combines DCF valuation, market and precedent cross-checks, deal structure, synergies, and DD risk adjustments.',
            metadata: [
                'primary_method' => 'dcf',
                'dcf_range_nzd' => $dcfRange,
                'market_multiple_cross_check_nzd' => $marketRange,
                'precedent_transaction_cross_check_nzd' => $precedentRange,
                'deal_structure_adjustment_nzd' => $dealStructureAdjustment,
                'synergy_adjustment_nzd' => $synergyAdjustment,
                'due_diligence_risk_adjustment_nzd' => $riskAdjustment,
                'purchase_price_range_nzd' => $purchaseRange,
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
        $impactMetrics = $this->npoImpactMetrics->reportMetrics($engagement);
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
                'impact_metrics' => $impactMetrics['payload'],
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
                body: $this->impactMetricLines($impactMetrics['payload'])
                    ?: 'No client-entered impact metrics have been recorded for this engagement yet.',
                sourceReference: 'impact_metrics:'.$engagement->getKey(),
                metadata: [
                    'metrics' => $impactMetrics['payload'],
                    'platform_metrics' => $impactMetrics['platform_metrics'],
                ],
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

    /**
     * @return array{0: Client, 1: Collection<int, NpoDimensionScore>}
     */
    private function npoReportInputs(NpoEngagement $engagement): array
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

        $scores = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('assessment_batch_id', $batchId)
            ->orderBy('dimension_number')
            ->get();

        if ($scores->isEmpty()) {
            throw new InvalidArgumentException('NPO reports require a recorded NPO health assessment.');
        }

        return [$client, $scores];
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function npoHealthSections(NpoEngagement $engagement, Collection $scores): array
    {
        $healthScore = (int) ($scores->first()?->health_score ?? round((float) $scores->avg('score')));
        $strongest = $scores->sortByDesc('score')->first();
        $priority = $scores->sortBy('score')->first();

        return [
            $this->generatedSection(
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
            $this->generatedSection(
                key: 'dimension_scores',
                title: 'Dimension scores',
                body: $scores
                    ->map(fn (NpoDimensionScore $score): string => "{$score->dimension_label}: {$score->score}/100")
                    ->implode("\n"),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: each dimension score is persisted with source attributions and advisor weighting.',
                metadata: ['dimension_score_ids' => $scores->pluck('id')->values()->all()],
            ),
            $this->generatedSection(
                key: 'priority_findings',
                title: 'Priority findings',
                body: $this->npoFindingLines($scores),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey().':findings',
                dataQualityNote: 'Data quality note: findings are copied from the scored assessment and should stay tied to their cited evidence.',
            ),
        ];
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function npoAdvisorSections(NpoEngagement $engagement, Collection $scores): array
    {
        $healthScore = (int) ($scores->first()?->health_score ?? round((float) $scores->avg('score')));
        $calculations = NpoValueCalculation::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByDesc('calculated_at')
            ->get()
            ->unique('type')
            ->values();

        return [
            $this->generatedSection(
                key: 'confidential_header',
                title: 'CONFIDENTIAL - NPO Advisor Report',
                body: 'CONFIDENTIAL advisor working paper. Header colour: Cognac. This report contains full workings and should not be released as the client-facing NPO Health Report.',
                sourceReference: 'npo_advisor_report:'.$engagement->getKey().':confidential',
                dataQualityNote: 'Data quality note: advisor-only report; release only through advisor-controlled channels.',
                metadata: ['confidential' => true, 'header_colour' => 'cognac'],
            ),
            $this->generatedSection(
                key: 'full_health_workings',
                title: 'Full NPO health workings',
                body: "Aggregate health score: {$healthScore}/100.\n\n".$scores
                    ->map(fn (NpoDimensionScore $score): string => "{$score->dimension_number}. {$score->dimension_label}: score {$score->score}, weight {$score->advisor_weight}, weighted {$score->weighted_score}.")
                    ->implode("\n"),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: advisor workings include weights and weighted scores from persisted assessment records.',
            ),
            $this->generatedSection(
                key: 'mission_roi_value_workings',
                title: 'Mission ROI value workings',
                body: $calculations->isEmpty()
                    ? 'No NPO value calculations have been recorded yet.'
                    : $calculations
                        ->map(fn (NpoValueCalculation $calculation): string => "{$calculation->type}: {$this->money($calculation->projection_mid)} midpoint, range {$this->money($calculation->projection_low)} to {$this->money($calculation->projection_high)}. Mission framing: ".(string) ($calculation->result['mission_framing'] ?? 'Mission impact framing retained.'))
                        ->implode("\n"),
                sourceReference: 'npo_value_calculations:'.$engagement->getKey(),
                dataQualityNote: 'Data quality note: values include the mandatory +/-15% uncertainty range and are framed as mission ROI, not commercial profit.',
                metadata: ['npo_value_calculation_ids' => $calculations->pluck('id')->values()->all()],
            ),
            $this->generatedSection(
                key: 'advisor_recommendation_frame',
                title: 'Advisor recommendation frame',
                body: $this->npoFindingLines($scores),
                sourceReference: 'npo_dimension_scores:'.$engagement->getKey().':findings',
                dataQualityNote: 'Data quality note: recommendation framing must preserve source evidence and avoid inflating mission outcomes.',
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function socialEnterpriseDualSections(NpoSocialEnterpriseScorecard $scorecard, NpoTensionAnalysis $analysis): array
    {
        return [
            $this->generatedSection(
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
                    'scorecard_id' => $scorecard->getKey(),
                    'commercial_axes' => $scorecard->commercial_axes,
                    'mission_axes' => $scorecard->mission_axes,
                ],
            ),
            $this->generatedSection(
                key: 'evidenced_tensions',
                title: 'Evidenced tensions',
                body: collect($analysis->tensions)
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
                metadata: ['tensions' => $analysis->tensions],
            ),
            $this->generatedSection(
                key: 'tension_evidence',
                title: 'Tension evidence',
                body: collect($analysis->tensions)
                    ->flatMap(fn (array $tension): array => (array) ($tension['data_points'] ?? []))
                    ->map(fn (mixed $point): string => is_array($point)
                        ? ((string) ($point['label'] ?? $point['key'] ?? 'Data point')).': '.(string) ($point['value'] ?? '').' ('.(string) ($point['source_reference'] ?? 'source').')'
                        : (string) $point)
                    ->implode("\n"),
                sourceReference: 'npo_tension_analyses:'.$analysis->getKey().':data_points',
                dataQualityNote: 'Data quality note: tension evidence is copied from the reviewed analysis record.',
            ),
        ];
    }

    /**
     * @param  Collection<int, NpoDimensionScore>  $scores
     */
    private function npoFindingLines(Collection $scores): string
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
     * @param  array<int, array<string, mixed>>  $metrics
     */
    private function impactMetricLines(array $metrics): string
    {
        return collect($metrics)
            ->map(function (array $metric): string {
                $value = $metric['value'] ?? null;
                $unit = trim((string) ($metric['unit'] ?? ''));
                $label = (string) ($metric['metric_label'] ?? $metric['metric_key'] ?? 'Metric');
                $platform = $metric['platform_value'] ?? null;
                $suffix = $unit === '' ? '' : " {$unit}";
                $platformNote = $platform === null ? '' : " (platform cap {$platform}{$suffix})";

                return "{$label}: {$value}{$suffix}{$platformNote}";
            })
            ->implode("\n");
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
        $mid = data_get($valuation?->normalised_values, 'reconciled.mid')
            ?? data_get($valuation?->normalised_values, 'mid');

        return is_numeric($mid) ? (float) $mid : null;
    }

    /**
     * @return array{low:float, mid:float, high:float}|null
     */
    private function ddValuationRange(DdValuation $valuation, string $key): ?array
    {
        $range = $key === 'reconciled'
            ? (data_get($valuation->normalised_values, 'reconciled') ?? $valuation->normalised_values)
            : data_get($valuation->normalised_values, $key);

        if (! is_array($range) && $valuation->businessValuation !== null) {
            $sourceRange = match ($key) {
                'sde_value' => $valuation->businessValuation->sde_value,
                'ebitda_value' => $valuation->businessValuation->ebitda_value,
                'dcf_value' => $valuation->businessValuation->dcf_value,
                default => null,
            };

            $range = is_array($sourceRange)
                ? $this->convertRangeToNzd($sourceRange, $valuation->source_to_nzd_rate)
                : null;
        }

        if (! is_array($range)) {
            return null;
        }

        foreach (['low', 'mid', 'high'] as $point) {
            if (! is_numeric($range[$point] ?? null)) {
                return null;
            }
        }

        return [
            'low' => round((float) $range['low'], 2),
            'mid' => round((float) $range['mid'], 2),
            'high' => round((float) $range['high'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $range
     * @return array<string, mixed>
     */
    private function convertRangeToNzd(array $range, float $rate): array
    {
        foreach (['low', 'mid', 'high'] as $point) {
            if (is_numeric($range[$point] ?? null)) {
                $range[$point] = round((float) $range[$point] * $rate, 2);
            }
        }

        return $range;
    }

    /**
     * @return array{low:float, mid:float, high:float}|null
     */
    private function ddMarketMultipleRange(DdValuation $valuation): ?array
    {
        $ranges = collect([
            $this->ddValuationRange($valuation, 'sde_value'),
            $this->ddValuationRange($valuation, 'ebitda_value'),
        ])->filter();

        if ($ranges->isEmpty()) {
            return null;
        }

        return [
            'low' => round((float) $ranges->avg('low'), 2),
            'mid' => round((float) $ranges->avg('mid'), 2),
            'high' => round((float) $ranges->avg('high'), 2),
        ];
    }

    /**
     * @return array{low:float, mid:float, high:float}|null
     */
    private function ddPrecedentTransactionRange(DdEngagement $engagement, DdValuation $valuation): ?array
    {
        $precedents = data_get($engagement->target_details, 'precedent_transactions', []);
        if (! is_array($precedents) || $precedents === []) {
            $precedents = data_get($valuation->buyer_position, 'precedent_transactions', []);
        }

        if (! is_array($precedents) || $precedents === []) {
            return null;
        }

        if ($this->hasRangePoints($precedents)) {
            return [
                'low' => round((float) $precedents['low'], 2),
                'mid' => round((float) $precedents['mid'], 2),
                'high' => round((float) $precedents['high'], 2),
            ];
        }

        $ebitda = data_get($valuation->businessValuation?->ebitda_value, 'input');
        $values = collect($precedents)
            ->filter('is_array')
            ->map(function (array $precedent) use ($ebitda, $valuation): ?float {
                $amount = $precedent['enterprise_value_nzd']
                    ?? $precedent['value_nzd']
                    ?? $precedent['amount_nzd']
                    ?? null;

                if (! is_numeric($amount) && is_numeric($precedent['amount'] ?? null)) {
                    $amount = (float) $precedent['amount'] * $valuation->source_to_nzd_rate;
                }

                if (! is_numeric($amount) && is_numeric($precedent['multiple'] ?? null) && is_numeric($ebitda)) {
                    $amount = (float) $precedent['multiple'] * (float) $ebitda * $valuation->source_to_nzd_rate;
                }

                return is_numeric($amount) ? round((float) $amount, 2) : null;
            })
            ->filter(fn (?float $amount): bool => $amount !== null)
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return [
            'low' => round((float) $values->min(), 2),
            'mid' => round((float) $values->avg(), 2),
            'high' => round((float) $values->max(), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $range
     */
    private function hasRangePoints(array $range): bool
    {
        return is_numeric($range['low'] ?? null)
            && is_numeric($range['mid'] ?? null)
            && is_numeric($range['high'] ?? null);
    }

    private function ddAdjustmentTotal(mixed ...$groups): float
    {
        return round(collect($groups)
            ->flatMap(function (mixed $group): array {
                if (! is_array($group)) {
                    return [];
                }

                if (isset($group['amount']) || isset($group['value'])) {
                    return [$group];
                }

                return array_values(array_filter($group, 'is_array'));
            })
            ->filter('is_array')
            ->sum(fn (array $adjustment): float => (float) ($adjustment['amount'] ?? $adjustment['value'] ?? 0)), 2);
    }

    /**
     * @param  array{low:float, mid:float, high:float}  $range
     * @return array{low:float, mid:float, high:float}
     */
    private function applyPurchasePriceAdjustments(
        array $range,
        float $dealStructureAdjustment,
        float $synergyAdjustment,
        float $riskAdjustment,
    ): array {
        $netAdjustment = $dealStructureAdjustment + $synergyAdjustment - $riskAdjustment;

        return [
            'low' => round(max(0, $range['low'] + $netAdjustment), 2),
            'mid' => round(max(0, $range['mid'] + $netAdjustment), 2),
            'high' => round(max(0, $range['high'] + $netAdjustment), 2),
        ];
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
        $report->loadMissing(['client.primaryContact', 'entrepreneurProfile', 'sections']);
        $sections = $report->sections
            ->sortBy('position')
            ->map(fn (ReportSection $section): string => $this->sectionHtml($section))
            ->implode('');
        $template = $this->templateForReport($report);
        $this->syncReportTemplateMetadata($report, $template);

        if ($template instanceof Template && $this->isTokenizedHtmlTemplate($template)) {
            return $this->htmlFromTemplate($report, $template, $sections);
        }

        if ($template instanceof Template) {
            $html = $this->uploadedTemplates->render(
                $report,
                $template,
                $sections,
                $this->reportTemplateTokens($report, $template, $sections),
                $this->reportCss($template),
            );

            if (is_string($html)) {
                return $html;
            }
        }

        return $this->brandedReportHtml($report, $template, $sections);
    }

    private function templateForReport(Report $report): ?Template
    {
        return $report->type instanceof ReportType
            ? $this->activeReportTemplateFor($report->type)
            : null;
    }

    private function syncReportTemplateMetadata(Report $report, ?Template $template): void
    {
        $metadata = $report->metadata ?? [];
        $templateMetadata = $this->reportTemplateMetadata($template);

        if (($metadata['template'] ?? null) === $templateMetadata) {
            return;
        }

        $metadata['template'] = $templateMetadata;
        $report->forceFill(['metadata' => $metadata])->save();
    }

    private function isTokenizedHtmlTemplate(Template $template): bool
    {
        $body = Str::lower((string) $template->body);

        return Str::contains($body, [
            '{{ sections',
            '{{sections',
            '<html',
            '<body',
            '<style',
            'data-report-template',
        ]);
    }

    private function htmlFromTemplate(Report $report, Template $template, string $sections): string
    {
        $body = (string) $template->body;
        $hasSectionsToken = Str::contains($body, [
            '{{ sections }}',
            '{{sections}}',
            '{{{ sections }}}',
            '{{{sections}}}',
        ]);
        $rendered = strtr($body, $this->reportTemplateTokens($report, $template, $sections));

        if (! $hasSectionsToken) {
            $rendered .= "\n".$sections;
        }

        if (Str::contains(Str::lower($rendered), '<html')) {
            return $rendered;
        }

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>%s</style>
</head>
<body data-report-template="%s">
%s
</body>
</html>
HTML,
            $this->escape($report->title),
            $this->reportCss($template),
            $this->escape((string) $template->getKey()),
            $rendered,
        );
    }

    /**
     * @return array<string, string>
     */
    private function reportTemplateTokens(Report $report, Template $template, string $sections): array
    {
        $clientName = $report->client?->legal_name ?? $report->entrepreneurProfile?->name ?? 'Client';
        $generatedAt = $report->generated_at?->format('j M Y') ?? now()->format('j M Y');
        $primaryContact = $report->client?->primaryContact?->name ?: 'Client contact';
        $primaryTitle = $report->client?->primaryContact instanceof User ? 'Primary contact' : 'Client';
        $engagementPeriod = is_string(data_get($report->metadata, 'engagement_period'))
            ? (string) data_get($report->metadata, 'engagement_period')
            : 'As at '.$generatedAt;

        return [
            '{{ report_title }}' => $this->escape($report->title),
            '{{report_title}}' => $this->escape($report->title),
            '{{ report_type }}' => $this->escape($report->type->label()),
            '{{report_type}}' => $this->escape($report->type->label()),
            '{{ client_name }}' => $this->escape($clientName),
            '{{client_name}}' => $this->escape($clientName),
            '{{ generated_at }}' => $this->escape($generatedAt),
            '{{generated_at}}' => $this->escape($generatedAt),
            '{{ template_title }}' => $this->escape($template->title),
            '{{template_title}}' => $this->escape($template->title),
            '{{ template_version }}' => (string) $template->version,
            '{{template_version}}' => (string) $template->version,
            '{{ sections }}' => $sections,
            '{{sections}}' => $sections,
            '{{{ sections }}}' => $sections,
            '{{{sections}}}' => $sections,
            '[Business Name]' => $this->escape($clientName),
            '[Report Type]' => $this->escape($report->type->label()),
            '[Date]' => $this->escape($generatedAt),
            '[Engagement Period]' => $this->escape($engagementPeriod),
            '[Client Primary Contact]' => $this->escape($primaryContact),
            '[Title]' => $this->escape($primaryTitle),
        ];
    }

    private function brandedReportHtml(Report $report, ?Template $template, string $sections): string
    {
        $clientName = $report->client?->legal_name ?? $report->entrepreneurProfile?->name ?? 'Client';
        $templateLabel = $template instanceof Template
            ? sprintf('%s v%d', $template->title, $template->version)
            : 'System report layout';

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>%s</style>
</head>
<body data-report-template="%s">
<header class="report-cover">
<div class="brand-lockup">
<div class="brand-mark"><span></span><span></span><span></span></div>
<div>
<p class="eyebrow">Future Shift Advisory</p>
<h1>%s</h1>
<p class="client-name">%s</p>
</div>
</div>
<dl class="report-meta">
<div><dt>Report type</dt><dd>%s</dd></div>
<div><dt>Generated</dt><dd>%s</dd></div>
<div><dt>Template</dt><dd>%s</dd></div>
</dl>
</header>
<main class="report-content">
%s
</main>
<footer class="report-footer">Generated using %s</footer>
</body>
</html>
HTML,
            $this->escape($report->title),
            $this->reportCss($template),
            $template instanceof Template ? $this->escape((string) $template->getKey()) : 'system',
            $this->escape($report->title),
            $this->escape($clientName),
            $this->escape($report->type->label()),
            $this->escape($report->generated_at?->format('j M Y') ?? now()->format('j M Y')),
            $this->escape($templateLabel),
            $sections,
            $this->escape($templateLabel),
        );
    }

    private function reportCss(?Template $template): string
    {
        $accent = $this->templateLayoutColor($template, 'accent_color', '#2f6f5e');
        $accentDark = $this->templateLayoutColor($template, 'accent_dark', '#153f36');
        $ink = $this->templateLayoutColor($template, 'ink_color', '#17211b');
        $muted = $this->templateLayoutColor($template, 'muted_color', '#5d6b63');
        $paper = $this->templateLayoutColor($template, 'paper_color', '#fbfcfb');

        return <<<CSS
@page { margin: 16mm 15mm 18mm; }
* { box-sizing: border-box; }
body { background: {$paper}; color: {$ink}; font-family: Arial, sans-serif; font-size: 11.5px; line-height: 1.55; margin: 0; }
.report-cover { border-top: 7px solid {$accent}; margin-bottom: 22px; padding-top: 18px; }
.brand-lockup { align-items: center; display: flex; gap: 14px; }
.brand-mark { align-items: end; display: inline-flex; gap: 3px; height: 36px; width: 38px; }
.brand-mark span { background: {$accent}; border-radius: 1px 1px 0 0; display: block; width: 8px; }
.brand-mark span:nth-child(1) { height: 14px; opacity: .55; }
.brand-mark span:nth-child(2) { height: 24px; opacity: .78; }
.brand-mark span:nth-child(3) { height: 34px; }
.eyebrow { color: {$accentDark}; font-size: 10px; font-weight: 700; letter-spacing: .08em; margin: 0 0 3px; text-transform: uppercase; }
.report-cover h1 { color: {$ink}; font-size: 25px; line-height: 1.15; margin: 0; }
.client-name { color: {$muted}; font-size: 12px; margin: 5px 0 0; }
.report-meta { border-bottom: 1px solid #d7e2dd; border-top: 1px solid #d7e2dd; display: grid; gap: 12px; grid-template-columns: repeat(3, 1fr); margin: 20px 0 0; padding: 10px 0; }
.report-meta div { min-width: 0; }
.report-meta dt { color: {$muted}; font-size: 9px; font-weight: 700; margin: 0 0 2px; text-transform: uppercase; }
.report-meta dd { margin: 0; }
.report-content { display: grid; gap: 17px; }
.report-section { background: #fff; border-left: 4px solid {$accent}; break-inside: avoid; padding: 0 0 0 14px; }
.report-section h2 { color: {$accentDark}; font-size: 16px; line-height: 1.3; margin: 0 0 7px; }
.section-body { white-space: pre-wrap; }
.section-body p { margin: 0 0 8px; }
.chart { margin: 12px 0; }
.evidence { border-top: 1px solid #e2ebe7; color: {$muted}; font-size: 9.5px; margin-top: 10px; padding-top: 7px; }
.evidence p { margin: 0 0 3px; }
.report-footer { border-top: 1px solid #d7e2dd; color: {$muted}; font-size: 9px; margin-top: 24px; padding-top: 8px; text-align: right; }
CSS;
    }

    private function templateLayoutColor(?Template $template, string $key, string $default): string
    {
        $value = $template instanceof Template ? data_get($template->structure, 'layout.'.$key) : null;

        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $default;
        }

        return $value;
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
<article class="report-section" data-section-key="%s">
<h2>%s</h2>
<div class="section-body">%s</div>
%s
<div class="evidence">
<p>%s</p>
<p>%s</p>
<p>Sources: %s</p>
</div>
</article>
HTML,
            $this->escape($section->key),
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
