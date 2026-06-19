<?php

declare(strict_types=1);

namespace App\Services\StandardAdvisory;

use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\AnalysisRun;
use App\Models\BusinessHealthSnapshot;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Contracts\AnalysisModule as AnalysisModuleContract;
use App\Services\Analysis\Modules\CompetitorAnalysis;
use App\Services\Analysis\Modules\ComplianceChecker;
use App\Services\Analysis\Modules\FinancialAnalysis;
use App\Services\Analysis\Modules\HrAnalysis;
use App\Services\Analysis\Modules\InsuranceRiskFlags;
use App\Services\Analysis\Modules\OperationalAnalysis;
use App\Services\Analysis\Modules\StrategicMatrices;
use App\Services\Analysis\Modules\SystemsReview;
use App\Services\Analysis\Modules\WebsiteAudit;
use App\Services\Audit\AuditWriter;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Reports\ReportComposer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class StandardAdvisoryWorkflow
{
    /**
     * @var array<string, class-string<AnalysisModuleContract>>
     */
    private const ANALYSIS_MODULES = [
        AnalysisModule::Financial->value => FinancialAnalysis::class,
        AnalysisModule::Operational->value => OperationalAnalysis::class,
        AnalysisModule::Systems->value => SystemsReview::class,
        AnalysisModule::Hr->value => HrAnalysis::class,
        AnalysisModule::Swot->value => StrategicMatrices::class,
        AnalysisModule::Competitor->value => CompetitorAnalysis::class,
        AnalysisModule::WebsiteAudit->value => WebsiteAudit::class,
        AnalysisModule::Compliance->value => ComplianceChecker::class,
        AnalysisModule::InsuranceRisk->value => InsuranceRiskFlags::class,
    ];

    public function __construct(
        private readonly AnalysisRunner $analysis,
        private readonly AuditWriter $audit,
        private readonly BusinessHealthSnapshotWriter $health,
        private readonly DataQualityScorer $dataQuality,
        private readonly ReportComposer $reports,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        if (! $this->isStandardAdvisory($client)) {
            return null;
        }

        $readiness = $this->readiness($client);

        return [
            ...$readiness,
            'run_analysis_url' => route('advisor.clients.standard-advisory.analysis', $client, absolute: false),
            'generate_pack_url' => route('advisor.clients.standard-advisory.pack', $client, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function portalSummary(Client $client): ?array
    {
        if (! $this->isStandardAdvisory($client)) {
            return null;
        }

        $readiness = $this->readiness($client);

        return [
            'status' => $readiness['status'],
            'status_label' => $readiness['status_label'],
            'next_action' => $readiness['next_action'],
            'missing' => $readiness['missing'],
            'questionnaire_submitted' => $readiness['questionnaire_submitted'],
            'document_count' => $readiness['document_count'],
            'client_report' => $this->portalClientReport($readiness['reports']['client']),
            'latest_report_generated_at' => $readiness['latest_report_generated_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(Client $client): array
    {
        $questionnaire = $this->latestStandardQuestionnaire();
        $response = $this->latestQuestionnaireResponse($client);
        $documents = $this->documents($client);
        $verifications = $this->verifications($client);
        $blockingVerifications = $verifications->filter(fn (DocumentVerification $verification): bool => $verification->isBlockingAnalysis());
        $analysisModules = $this->analysisModuleSummaries($client);
        $analysisCompleted = collect($analysisModules)->where('completed', true)->count();
        $healthBatch = $this->latestHealthBatch($client);
        $reports = $this->reportSummaries($client);
        $dataQuality = $this->dataQuality->score($client);
        $latestValuation = $this->latestValuation($client);

        $missing = [];
        if (! $response instanceof QuestionnaireResponse) {
            $missing[] = 'Submit the Standard Advisory questionnaire.';
        }
        if ($documents->isEmpty()) {
            $missing[] = 'Upload supporting documents for the advisory review.';
        }
        if ($blockingVerifications->isNotEmpty()) {
            $missing[] = 'Resolve document verification flags before relying on analysis.';
        }
        if ($analysisCompleted === 0) {
            $missing[] = 'Run Standard Advisory analysis.';
        }
        if (! $healthBatch instanceof Collection) {
            $missing[] = 'Recompute the business health radar from the latest analysis.';
        }
        if ($reports['advisor'] === null || $reports['client'] === null) {
            $missing[] = 'Generate the advisory pack.';
        }
        if (($reports['client']['review_status'] ?? null) === 'pending_review') {
            $missing[] = 'Review and release the client report.';
        }

        return [
            'questionnaire_submitted' => $response instanceof QuestionnaireResponse,
            'questionnaire_submitted_at' => $response?->submitted_at?->toIso8601String(),
            'answered_questions' => $response instanceof QuestionnaireResponse ? $response->answers()->count() : 0,
            'total_questions' => $questionnaire instanceof Questionnaire
                ? $questionnaire->sections->flatMap(fn ($section) => $section->questions)->count()
                : 0,
            'document_count' => $documents->count(),
            'verified_document_count' => $this->verifiedDocumentCount($documents),
            'blocking_verification_count' => $blockingVerifications->count(),
            'data_quality' => [
                'level' => $dataQuality->level,
                'score' => $dataQuality->score,
                'summary' => $dataQuality->toPayload(),
            ],
            'analysis_modules' => $analysisModules,
            'analysis_completed' => $analysisCompleted,
            'analysis_total' => count(self::ANALYSIS_MODULES),
            'health_recomputed_at' => $healthBatch instanceof Collection
                ? $healthBatch->first()?->captured_at?->toIso8601String()
                : null,
            'valuation_ready' => $latestValuation instanceof BusinessValuation,
            'valuation_as_at' => $latestValuation?->as_at?->toIso8601String(),
            'reports' => $reports,
            'latest_report_generated_at' => $this->latestReportGeneratedAt($reports),
            'missing' => $missing,
            'can_run_analysis' => $response instanceof QuestionnaireResponse
                && $documents->isNotEmpty()
                && $blockingVerifications->isEmpty(),
            'can_generate_pack' => $analysisCompleted > 0
                && $blockingVerifications->isEmpty(),
            'status' => $this->status($response, $documents, $blockingVerifications, $analysisCompleted, $reports),
            'status_label' => $this->statusLabel($response, $documents, $blockingVerifications, $analysisCompleted, $reports),
            'next_action' => $this->nextAction($response, $documents, $blockingVerifications, $analysisCompleted, $reports),
        ];
    }

    /**
     * @return array<int, AnalysisRun>
     */
    public function runAnalysis(Client $client, User $actor): array
    {
        $this->assertStandardAdvisory($client);
        $this->assertAnalysisReady($client);

        $runs = [];

        foreach (self::ANALYSIS_MODULES as $moduleClass) {
            $runs[] = $this->analysis->run(
                $client,
                app($moduleClass),
                [
                    'actor' => $actor,
                    'created_by_user_id' => $actor->getKey(),
                ],
            );
        }

        $snapshots = $this->health->recompute($client);

        $this->audit->record('standard_advisory.analysis_run', subject: $client, actor: $actor, after: [
            'analysis_run_ids' => array_map(static fn (AnalysisRun $run): string => (string) $run->getKey(), $runs),
            'health_batch_id' => (string) $snapshots->first()?->assessment_batch_id,
        ]);

        return $runs;
    }

    /**
     * @return array<string, Report>
     */
    public function generateAdvisoryPack(Client $client, User $actor): array
    {
        $this->assertStandardAdvisory($client);
        $this->assertPackReady($client);

        $reports = [
            'advisor' => $this->reports->compose($client, ReportType::Advisor, $actor),
            'client' => $this->reports->compose($client, ReportType::Client, $actor),
            'stakeholder' => $this->reports->compose($client, ReportType::Stakeholder, $actor),
            'trajectory' => $this->reports->compose($client, ReportType::Trajectory, $actor),
        ];

        $this->audit->record('standard_advisory.pack_generated', subject: $client, actor: $actor, after: [
            'report_ids' => array_map(static fn (Report $report): string => (string) $report->getKey(), $reports),
        ]);

        return $reports;
    }

    private function isStandardAdvisory(Client $client): bool
    {
        $type = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $type === EngagementType::STANDARD_ADVISORY;
    }

    private function assertStandardAdvisory(Client $client): void
    {
        if (! $this->isStandardAdvisory($client)) {
            throw ValidationException::withMessages([
                'standard_advisory' => 'This workflow is only available for Standard Advisory clients.',
            ]);
        }
    }

    private function assertAnalysisReady(Client $client): void
    {
        $readiness = $this->readiness($client);

        if ($readiness['can_run_analysis'] === true) {
            return;
        }

        throw ValidationException::withMessages([
            'standard_advisory' => implode(' ', $readiness['missing']),
        ]);
    }

    private function assertPackReady(Client $client): void
    {
        $readiness = $this->readiness($client);

        if ($readiness['can_generate_pack'] === true) {
            return;
        }

        throw ValidationException::withMessages([
            'standard_advisory' => implode(' ', $readiness['missing']),
        ]);
    }

    private function latestStandardQuestionnaire(): ?Questionnaire
    {
        return Questionnaire::query()
            ->forSet(QuestionnaireSet::STANDARD_ADVISORY)
            ->published()
            ->with('sections.questions')
            ->latest('published_at')
            ->latest()
            ->first();
    }

    private function latestQuestionnaireResponse(Client $client): ?QuestionnaireResponse
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::STANDARD_ADVISORY))
            ->with('answers')
            ->latest('submitted_at')
            ->latest()
            ->first();
    }

    /**
     * @return Collection<int, Document>
     */
    private function documents(Client $client): Collection
    {
        return Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->with('verifications')
            ->get();
    }

    /**
     * @return Collection<int, DocumentVerification>
     */
    private function verifications(Client $client): Collection
    {
        return DocumentVerification::query()
            ->where('client_id', $client->getKey())
            ->get();
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function verifiedDocumentCount(Collection $documents): int
    {
        return $documents
            ->filter(fn (Document $document): bool => $document->verifications->isNotEmpty()
                && $document->verifications->every(
                    fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
                ))
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function analysisModuleSummaries(Client $client): array
    {
        return collect(self::ANALYSIS_MODULES)
            ->map(function (string $moduleClass, string $module): array {
                $moduleEnum = AnalysisModule::from($module);

                return [
                    'module' => $module,
                    'label' => $this->analysisModuleLabel($moduleEnum),
                    'class' => $moduleClass,
                ];
            })
            ->map(function (array $module) use ($client): array {
                $run = AnalysisRun::query()
                    ->where('client_id', $client->getKey())
                    ->where('module', $module['module'])
                    ->latest('completed_at')
                    ->latest()
                    ->first();

                return [
                    'module' => $module['module'],
                    'label' => $module['label'],
                    'status' => $run?->status ?? AnalysisRun::STATUS_QUEUED,
                    'completed' => $run?->status === AnalysisRun::STATUS_COMPLETED,
                    'completed_at' => $run?->completed_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, BusinessHealthSnapshot>|null
     */
    private function latestHealthBatch(Client $client): ?Collection
    {
        $batch = BusinessHealthSnapshot::query()
            ->select('assessment_batch_id')
            ->where('client_id', $client->getKey())
            ->latest('captured_at')
            ->latest()
            ->first();

        if (! $batch instanceof BusinessHealthSnapshot) {
            return null;
        }

        return BusinessHealthSnapshot::query()
            ->where('client_id', $client->getKey())
            ->where('assessment_batch_id', $batch->assessment_batch_id)
            ->get();
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function reportSummaries(Client $client): array
    {
        return collect([
            'client' => ReportType::Client,
            'advisor' => ReportType::Advisor,
            'stakeholder' => ReportType::Stakeholder,
            'trajectory' => ReportType::Trajectory,
        ])
            ->map(fn (ReportType $type): ?array => $this->latestReport($client, $type))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestReport(Client $client, ReportType $type): ?array
    {
        $report = Report::query()
            ->where('client_id', $client->getKey())
            ->where('type', $type->value)
            ->latest('generated_at')
            ->latest()
            ->first();

        if (! $report instanceof Report) {
            return null;
        }

        return [
            'id' => $report->id,
            'type' => $report->type->value,
            'type_label' => $report->type->label(),
            'title' => $report->title,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'review_status' => $report->review_status,
            'reviewed_at' => $report->reviewed_at?->toIso8601String(),
            'download_url' => route('advisor.reports.download', $report, absolute: false),
            'review_url' => route('advisor.reports.review', $report, absolute: false),
            'release_url' => $type === ReportType::Client
                ? route('advisor.reports.release', $report, absolute: false)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, mixed>|null
     */
    private function portalClientReport(?array $report): ?array
    {
        if ($report === null) {
            return null;
        }

        return [
            'id' => $report['id'],
            'type' => $report['type'],
            'type_label' => $report['type_label'],
            'title' => $report['title'],
            'generated_at' => $report['generated_at'],
            'review_status' => $report['review_status'],
            'reviewed_at' => $report['reviewed_at'],
            'download_url' => in_array($report['review_status'], ['not_required', 'reviewed'], true)
                ? route('portal.reports.show', $report['id'], absolute: false)
                : null,
        ];
    }

    private function latestValuation(Client $client): ?BusinessValuation
    {
        return BusinessValuation::query()
            ->where('client_id', $client->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $reports
     */
    private function latestReportGeneratedAt(array $reports): ?string
    {
        return collect($reports)
            ->filter()
            ->pluck('generated_at')
            ->filter()
            ->sortDesc()
            ->first();
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @param  Collection<int, DocumentVerification>  $blockingVerifications
     * @param  array<string, array<string, mixed>|null>  $reports
     */
    private function status(
        ?QuestionnaireResponse $response,
        Collection $documents,
        Collection $blockingVerifications,
        int $analysisCompleted,
        array $reports,
    ): string {
        if (! $response instanceof QuestionnaireResponse) {
            return 'waiting_questionnaire';
        }

        if ($documents->isEmpty()) {
            return 'waiting_documents';
        }

        if ($blockingVerifications->isNotEmpty()) {
            return 'verification_blocked';
        }

        if ($analysisCompleted === 0) {
            return 'ready_for_analysis';
        }

        if ($reports['client'] === null || $reports['advisor'] === null) {
            return 'ready_for_pack';
        }

        if (($reports['client']['review_status'] ?? null) === 'pending_review') {
            return 'awaiting_report_release';
        }

        return 'client_report_released';
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @param  Collection<int, DocumentVerification>  $blockingVerifications
     * @param  array<string, array<string, mixed>|null>  $reports
     */
    private function statusLabel(
        ?QuestionnaireResponse $response,
        Collection $documents,
        Collection $blockingVerifications,
        int $analysisCompleted,
        array $reports,
    ): string {
        return match ($this->status($response, $documents, $blockingVerifications, $analysisCompleted, $reports)) {
            'waiting_questionnaire' => 'Waiting for questionnaire',
            'waiting_documents' => 'Waiting for evidence',
            'verification_blocked' => 'Evidence review needed',
            'ready_for_analysis' => 'Ready for analysis',
            'ready_for_pack' => 'Ready for advisory pack',
            'awaiting_report_release' => 'Report awaiting release',
            default => 'Client report released',
        };
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @param  Collection<int, DocumentVerification>  $blockingVerifications
     * @param  array<string, array<string, mixed>|null>  $reports
     */
    private function nextAction(
        ?QuestionnaireResponse $response,
        Collection $documents,
        Collection $blockingVerifications,
        int $analysisCompleted,
        array $reports,
    ): string {
        return match ($this->status($response, $documents, $blockingVerifications, $analysisCompleted, $reports)) {
            'waiting_questionnaire' => 'Ask the client to complete onboarding.',
            'waiting_documents' => 'Ask the client to upload supporting evidence.',
            'verification_blocked' => 'Resolve document verification flags.',
            'ready_for_analysis' => 'Run Standard Advisory analysis.',
            'ready_for_pack' => 'Generate the advisory pack.',
            'awaiting_report_release' => 'Review and release the client report.',
            default => 'Discuss the released report with the client.',
        };
    }

    private function analysisModuleLabel(AnalysisModule $module): string
    {
        return str($module->value)->replace('_', ' ')->title()->toString();
    }
}
