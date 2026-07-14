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
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\StandardAdvisoryPackWaiver;
use App\Models\User;
use App\Models\WebsiteAuditSnapshot;
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
use App\Services\Analysis\WebsiteAuditRunner;
use App\Services\Analysis\WebsiteAuditSnapshotStore;
use App\Services\Analysis\WebsiteUrlConfirmationService;
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
        private readonly WebsiteAuditRunner $websiteAudit,
        private readonly AuditWriter $audit,
        private readonly BusinessHealthSnapshotWriter $health,
        private readonly DataQualityScorer $dataQuality,
        private readonly ReportComposer $reports,
        private readonly WebsiteUrlConfirmationService $websiteUrls,
        private readonly WebsiteAuditSnapshotStore $websiteSnapshots,
    ) {}

    /**
     * @return array<int, string>
     */
    public function requiredAnalysisModuleValues(): array
    {
        return array_keys(self::ANALYSIS_MODULES);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        if (! $this->isStandardAdvisory($client)) {
            return null;
        }

        $readiness = $this->readiness($client);
        $readiness['website_audit']['confirm_url'] = route('advisor.clients.standard-advisory.website-url', $client, absolute: false);

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
            'momentum' => $readiness['momentum'],
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
        $waivers = $this->activePackWaivers($client);
        $analysisModules = $this->analysisModuleSummaries($client, $waivers);
        $analysisCompleted = collect($analysisModules)->where('completed', true)->count();
        $analysisWaived = collect($analysisModules)->where('waived', true)->count();
        $analysisDroppedFindings = (int) collect($analysisModules)->sum('dropped_findings.missing_attribution');
        $analysisReadyForPack = collect($analysisModules)->every(fn (array $module): bool => (bool) ($module['ready_for_pack'] ?? false));
        $verifiedDocumentCount = $this->verifiedDocumentCount($documents);
        $canRecordPackWaiver = $response instanceof QuestionnaireResponse
            && $documents->isNotEmpty()
            && $blockingVerifications->isEmpty()
            && ! $analysisReadyForPack
            && $analysisCompleted > 0;
        $healthBatch = $this->latestHealthBatch($client);
        $reports = $this->reportSummaries($client);
        $dataQuality = $this->dataQuality->score($client);
        $latestValuation = $this->latestValuation($client);
        $websiteAudit = $this->websiteAuditReadiness($client);
        $websiteConfirmationRequired = $websiteAudit['status'] === 'awaiting_confirmation';
        $canRunAnalysis = $response instanceof QuestionnaireResponse
            && $documents->isNotEmpty()
            && $blockingVerifications->isEmpty()
            && ! $websiteConfirmationRequired;
        $onboardingState = is_array($client->onboarding_wizard_state) ? $client->onboarding_wizard_state : [];
        $onboardingSubmitted = is_string($onboardingState['submitted_at'] ?? null)
            && trim((string) $onboardingState['submitted_at']) !== '';
        $analysisReadiness = $this->analysisReadiness(
            canRunAnalysis: $canRunAnalysis,
            onboardingSubmitted: $onboardingSubmitted,
        );
        $momentum = $this->momentum(
            onboardingState: $onboardingState,
            response: $response,
            documents: $documents,
            websiteAudit: $websiteAudit,
            clientReport: $reports['client'],
        );

        $missing = [];
        $warnings = [];
        if (! $response instanceof QuestionnaireResponse) {
            $missing[] = 'Submit the Standard Advisory questionnaire.';
        }
        if ($documents->isEmpty()) {
            $missing[] = 'Upload supporting documents for the advisory review.';
        }
        if ($blockingVerifications->isNotEmpty()) {
            $missing[] = 'Resolve document verification flags before relying on analysis.';
        }
        if ($websiteConfirmationRequired) {
            $missing[] = 'Confirm the client website URL before running the website review.';
        }
        if ($analysisCompleted === 0 && $analysisWaived === 0) {
            $missing[] = 'Run Standard Advisory analysis.';
        } elseif (! $analysisReadyForPack) {
            $missing[] = 'Complete or waive Standard Advisory analysis modules: '.$this->incompleteAnalysisModuleLabels($analysisModules).'.';
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
        if ($analysisDroppedFindings > 0) {
            $warnings[] = "{$analysisDroppedFindings} analysis finding(s) were dropped because source attribution was incomplete.";
        }

        return [
            'questionnaire_submitted' => $response instanceof QuestionnaireResponse,
            'questionnaire_submitted_at' => $response?->submitted_at?->toIso8601String(),
            'answered_questions' => $response instanceof QuestionnaireResponse ? $response->answers()->count() : 0,
            'total_questions' => $questionnaire instanceof Questionnaire
                ? $questionnaire->sections->flatMap(fn ($section) => $section->questions)->count()
                : 0,
            'document_count' => $documents->count(),
            'verified_document_count' => $verifiedDocumentCount,
            'blocking_verification_count' => $blockingVerifications->count(),
            'data_quality' => [
                'level' => $dataQuality->level,
                'score' => $dataQuality->score,
                'summary' => $dataQuality->toPayload(),
            ],
            'analysis_modules' => $analysisModules,
            'analysis_completed' => $analysisCompleted,
            'analysis_waived' => $analysisWaived,
            'analysis_dropped_findings' => $analysisDroppedFindings,
            'analysis_total' => count(self::ANALYSIS_MODULES),
            'analysis_ready_for_pack' => $analysisReadyForPack,
            'pack_waivers' => $waivers->map(fn (StandardAdvisoryPackWaiver $waiver): array => $this->waiverPayload($waiver))->values()->all(),
            'waivable_modules' => $this->waivableModuleValues($analysisModules),
            'website_audit' => $websiteAudit,
            'health_recomputed_at' => $healthBatch instanceof Collection
                ? $healthBatch->first()?->captured_at?->toIso8601String()
                : null,
            'valuation_ready' => $latestValuation instanceof BusinessValuation,
            'valuation_as_at' => $latestValuation?->as_at?->toIso8601String(),
            'reports' => $reports,
            'latest_report_generated_at' => $this->latestReportGeneratedAt($reports),
            'missing' => $missing,
            'warnings' => $warnings,
            'analysis_readiness' => $analysisReadiness,
            'momentum' => $momentum,
            'can_run_analysis' => $canRunAnalysis,
            'can_generate_pack' => $response instanceof QuestionnaireResponse
                && $documents->isNotEmpty()
                && $analysisReadyForPack
                && $blockingVerifications->isEmpty(),
            'can_record_pack_waiver' => $canRecordPackWaiver,
            'status' => $this->status($response, $documents, $blockingVerifications, $analysisCompleted, $analysisWaived, $analysisReadyForPack, $reports),
            'status_label' => $this->statusLabel($response, $documents, $blockingVerifications, $analysisCompleted, $analysisWaived, $analysisReadyForPack, $reports),
            'next_action' => $this->nextAction($response, $documents, $blockingVerifications, $analysisCompleted, $analysisWaived, $analysisReadyForPack, $reports),
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

        foreach (self::ANALYSIS_MODULES as $module => $moduleClass) {
            $options = [
                'actor' => $actor,
                'created_by_user_id' => $actor->getKey(),
            ];
            $runs[] = $module === AnalysisModule::WebsiteAudit->value
                ? $this->websiteAudit->run($client, $options)
                : $this->analysis->run($client, app($moduleClass), $options);
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

    /**
     * @param  array<int, string>  $modules
     */
    public function recordPackWaiver(Client $client, User $actor, array $modules, string $reason): StandardAdvisoryPackWaiver
    {
        $this->assertStandardAdvisory($client);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'waiver_reason' => 'Add the advisor reason before waiving incomplete Standard Advisory modules.',
            ]);
        }

        $readiness = $this->readiness($client);
        if ($readiness['can_generate_pack'] === true) {
            throw ValidationException::withMessages([
                'waiver_modules' => 'This advisory pack is already ready; no partial-pack waiver is required.',
            ]);
        }

        if ($readiness['can_record_pack_waiver'] !== true) {
            throw ValidationException::withMessages([
                'standard_advisory' => implode(' ', $readiness['missing']),
            ]);
        }

        $waivableModules = $this->waivableModuleValues($readiness['analysis_modules']);
        $modules = $this->normaliseWaiverModules($modules === [] ? $waivableModules : $modules);
        $invalid = array_values(array_diff($modules, $waivableModules));

        if ($modules === [] || $invalid !== []) {
            throw ValidationException::withMessages([
                'waiver_modules' => 'Choose only incomplete, failed, or stale Standard Advisory modules for the waiver.',
            ]);
        }

        $waiver = StandardAdvisoryPackWaiver::query()->create([
            'client_id' => $client->getKey(),
            'waived_by_user_id' => $actor->getKey(),
            'modules' => $modules,
            'reason' => $reason,
            'waived_at' => now(),
        ]);

        $this->audit->record('standard_advisory.pack_waiver_recorded', subject: $client, actor: $actor, after: [
            'waiver_id' => (string) $waiver->getKey(),
            'modules' => $modules,
            'reason' => $reason,
        ]);

        return $waiver;
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

    /**
     * @return array{level:'red'|'amber'|'green', label:string, description:string}
     */
    private function analysisReadiness(bool $canRunAnalysis, bool $onboardingSubmitted): array
    {
        if (! $canRunAnalysis) {
            return [
                'level' => 'red',
                'label' => 'Required client inputs incomplete',
                'description' => 'Analysis is blocked until the questionnaire, supporting documents, and any nominated website confirmation are ready.',
            ];
        }

        if (! $onboardingSubmitted) {
            return [
                'level' => 'amber',
                'label' => 'Minimum client inputs ready',
                'description' => 'Analysis can run now. Complete client onboarding for a full client pack.',
            ];
        }

        return [
            'level' => 'green',
            'label' => 'Complete client pack',
            'description' => 'The client has submitted all onboarding inputs and supporting evidence.',
        ];
    }

    /**
     * @param  array<string, mixed>  $onboardingState
     * @param  Collection<int, Document>  $documents
     * @param  array<string, mixed>  $websiteAudit
     * @param  array<string, mixed>|null  $clientReport
     * @return array{completed:int,total:int,percent:int,next_action:string,items:array<int,array{key:string,label:string,description:string,status:'complete'|'in_progress'|'waiting_advisor'|'not_required',owner:'client'|'advisor'}>}
     */
    private function momentum(
        array $onboardingState,
        ?QuestionnaireResponse $response,
        Collection $documents,
        array $websiteAudit,
        ?array $clientReport,
    ): array {
        $completedSteps = is_array($onboardingState['completed_steps'] ?? null)
            ? $onboardingState['completed_steps']
            : [];
        $onboardingSubmitted = is_string($onboardingState['submitted_at'] ?? null)
            && trim((string) $onboardingState['submitted_at']) !== '';
        $websiteStatus = (string) ($websiteAudit['status'] ?? 'waiting_questionnaire');
        $websiteComplete = in_array($websiteStatus, [
            WebsiteAuditSnapshot::STATUS_OK,
            WebsiteAuditSnapshot::STATUS_PARTIAL,
            WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL,
        ], true);
        $websiteNeedsAdvisor = $websiteStatus === 'awaiting_confirmation';
        $reportReleased = in_array((string) ($clientReport['review_status'] ?? ''), ['reviewed', 'not_required'], true);

        $items = [
            $this->momentumItem(
                key: 'goals',
                label: 'Set your goals',
                description: 'Share what a successful advisory engagement looks like for your business.',
                complete: in_array('goals', $completedSteps, true),
                owner: 'client',
            ),
            $this->momentumItem(
                key: 'website',
                label: 'Record your website details',
                description: 'Provide a public website address or record that your business does not have one.',
                complete: in_array('website', $completedSteps, true),
                owner: 'client',
            ),
            $this->momentumItem(
                key: 'questionnaire',
                label: 'Complete the questionnaire',
                description: 'Give your advisor the business context needed for a useful review.',
                complete: $response instanceof QuestionnaireResponse,
                owner: 'client',
            ),
            $this->momentumItem(
                key: 'evidence',
                label: 'Share supporting evidence',
                description: 'Upload the documents your advisor needs to assess the business.',
                complete: $documents->isNotEmpty(),
                owner: 'client',
            ),
            $this->momentumItem(
                key: 'onboarding',
                label: 'Submit onboarding',
                description: 'Confirm your details are ready for the advisory work to begin.',
                complete: $onboardingSubmitted,
                owner: 'client',
            ),
            [
                'key' => 'website_review',
                'label' => 'Website review',
                'description' => $websiteComplete
                    ? 'Your website review is recorded in the advisory evidence.'
                    : ($websiteNeedsAdvisor
                        ? 'Your advisor will confirm the nominated website before review.'
                        : 'Your advisor will complete or record the website review with analysis.'),
                'status' => $websiteComplete
                    ? ($websiteStatus === WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL ? 'not_required' : 'complete')
                    : ($websiteNeedsAdvisor ? 'waiting_advisor' : 'in_progress'),
                'owner' => 'advisor',
            ],
            $this->momentumItem(
                key: 'client_report',
                label: 'Review your advisory report',
                description: $reportReleased
                    ? 'Your advisory report is available to review with your advisor.'
                    : 'Your advisor will prepare and release your report after the review.',
                complete: $reportReleased,
                owner: 'advisor',
            ),
        ];

        $completed = collect($items)
            ->whereIn('status', ['complete', 'not_required'])
            ->count();
        $next = collect($items)
            ->first(fn (array $item): bool => $item['status'] !== 'complete' && $item['status'] !== 'not_required');

        return [
            'completed' => $completed,
            'total' => count($items),
            'percent' => (int) round(($completed / count($items)) * 100),
            'next_action' => is_array($next)
                ? $next['description']
                : 'Your advisory journey is complete. Keep working with your advisor on the agreed priorities.',
            'items' => $items,
        ];
    }

    /**
     * @return array{key:string,label:string,description:string,status:'complete'|'in_progress',owner:'client'|'advisor'}
     */
    private function momentumItem(
        string $key,
        string $label,
        string $description,
        bool $complete,
        string $owner,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => $complete ? 'complete' : 'in_progress',
            'owner' => $owner,
        ];
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
     * @return Collection<int, QuestionnaireResponse>
     */
    private function latestStandardQuestionnaireResponses(Client $client): Collection
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::STANDARD_ADVISORY))
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(3)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteAuditReadiness(Client $client): array
    {
        $responses = $this->latestStandardQuestionnaireResponses($client);
        $candidates = $this->websiteUrls->candidates($client);
        $confirmation = $this->websiteUrls->latestConfirmed($client);

        if ($responses->isEmpty()) {
            if ($confirmation === null && $candidates !== []) {
                return $this->websiteAuditReadinessPayload(
                    status: 'awaiting_confirmation',
                    label: 'Advisor confirmation needed',
                    nextAction: 'The client submitted a website URL. Confirm it now; the website review will run with analysis after the questionnaire and supporting evidence are complete.',
                    hasUrl: true,
                    candidates: $candidates,
                );
            }

            return $this->websiteAuditReadinessPayload(
                status: 'waiting_questionnaire',
                label: 'Waiting for questionnaire',
                nextAction: 'Ask the client to complete the Standard Advisory questionnaire before relying on website alignment findings.',
            );
        }

        $answers = $responses->flatMap(fn (QuestionnaireResponse $response): Collection => $response->answers);
        $productServiceAnswers = $answers->filter(fn (QuestionnaireAnswer $answer): bool => $this->isProductServiceAnswer($answer));
        $hasProductServiceEvidence = $productServiceAnswers->contains(
            fn (QuestionnaireAnswer $answer): bool => trim($this->answerValueText($answer)) !== '',
        );
        $snapshot = $confirmation === null ? $this->websiteSnapshots->latestForClient($client) : $this->websiteSnapshots->latestForConfirmation($confirmation);
        $hasUrl = $candidates !== [] || $confirmation !== null;
        $hasWebsitePageEvidence = is_array($snapshot?->pages) && $snapshot->pages !== [];
        $hasSeoEvidence = is_numeric(data_get($snapshot?->scores, 'findability'));

        if ($confirmation === null && $candidates === []) {
            return $this->websiteAuditReadinessPayload(
                status: 'missing_url',
                label: 'Website URL missing',
                nextAction: 'Capture a public website URL. The review will be skipped and noted in reports until one is listed and advisor-confirmed.',
                hasProductServiceEvidence: $hasProductServiceEvidence,
                candidates: [],
            );
        }

        if ($confirmation === null) {
            return $this->websiteAuditReadinessPayload(
                status: 'awaiting_confirmation',
                label: 'Advisor confirmation needed',
                nextAction: 'Confirm the nominated URL before the audit can fetch or assess the site.',
                hasUrl: true,
                hasProductServiceEvidence: $hasProductServiceEvidence,
                candidates: $candidates,
            );
        }

        if (! $snapshot instanceof WebsiteAuditSnapshot) {
            return $this->websiteAuditReadinessPayload(
                status: 'ready_to_fetch',
                label: 'Ready to fetch',
                nextAction: 'Run Standard Advisory analysis to fetch and evaluate the confirmed website.',
                hasUrl: true,
                hasProductServiceEvidence: $hasProductServiceEvidence,
                confirmedUrl: $confirmation->root_url,
                candidates: $candidates,
            );
        }

        if ($snapshot->fetch_status === WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL) {
            return $this->websiteAuditReadinessPayload(
                status: 'awaiting_confirmation',
                label: 'Advisor confirmation needed',
                nextAction: 'Confirm the nominated URL and run analysis. No website evaluation has been performed.',
                hasUrl: true,
                hasProductServiceEvidence: $hasProductServiceEvidence,
                confirmedUrl: $confirmation->root_url,
                candidates: $candidates,
            );
        }

        return $this->websiteAuditReadinessPayload(
            status: $snapshot->fetch_status,
            label: str($snapshot->fetch_status)->replace('_', ' ')->title()->toString(),
            nextAction: $snapshot->fetch_status === WebsiteAuditSnapshot::STATUS_OK
                ? 'Verified website evidence is available. Re-run after material website changes.'
                : 'The latest website review was incomplete. Review the report note and re-run when the site is available.',
            hasUrl: true,
            hasWebsitePageEvidence: $hasWebsitePageEvidence,
            hasProductServiceEvidence: $hasProductServiceEvidence,
            hasSeoEvidence: $hasSeoEvidence,
            confirmedUrl: $confirmation->root_url,
            candidates: $candidates,
            fetchStatus: $snapshot->fetch_status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteAuditReadinessPayload(
        string $status,
        string $label,
        string $nextAction,
        bool $hasUrl = false,
        bool $hasWebsitePageEvidence = false,
        bool $hasProductServiceEvidence = false,
        bool $hasSeoEvidence = false,
        ?string $confirmedUrl = null,
        array $candidates = [],
        ?string $fetchStatus = null,
    ): array {
        return [
            'status' => $status,
            'status_label' => $label,
            'next_action' => $nextAction,
            'has_url' => $hasUrl,
            'has_website_page_evidence' => $hasWebsitePageEvidence,
            'has_product_service_evidence' => $hasProductServiceEvidence,
            'has_seo_evidence' => $hasSeoEvidence,
            'confirmed_url' => $confirmedUrl,
            'candidates' => $candidates,
            'fetch_status' => $fetchStatus,
        ];
    }

    private function isWebsiteAuditAnswer(QuestionnaireAnswer $answer): bool
    {
        $haystack = $this->answerHaystack($answer);

        foreach (['website', 'url', 'seo', 'geo', 'aeo', 'aio', 'mobile', 'search', 'schema', 'structured data', 'answer engine', 'generative engine', 'ai overview', 'ai search', 'cta', 'call to action', 'landing page', 'product page', 'service page', 'enquiry'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return $this->hasWebsiteUrl($this->answerValueText($answer));
    }

    private function isProductServiceAnswer(QuestionnaireAnswer $answer): bool
    {
        $haystack = $this->answerHaystack($answer);

        foreach (['product', 'products', 'service', 'services', 'selling', 'sells', 'sold', 'offer', 'offers', 'price', 'pricing', 'package', 'packages', 'customer', 'customers', 'sales channel'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function answerHaystack(QuestionnaireAnswer $answer): string
    {
        return strtolower((string) $answer->question?->prompt.' '.$this->answerValueText($answer));
    }

    private function answerValueText(QuestionnaireAnswer $answer): string
    {
        if (is_array($answer->value)) {
            return (string) json_encode($answer->value);
        }

        return (string) $answer->value;
    }

    private function hasWebsiteUrl(string $text): bool
    {
        return preg_match('/https?:\/\/|www\.|[a-z0-9.-]+\.(?:co\.nz|nz|com|net|org)\b/i', $text) === 1;
    }

    private function hasWebsitePageEvidence(string $text): bool
    {
        $text = strtolower($text);

        foreach (['home page', 'homepage', 'service page', 'product page', 'pricing page', 'booking page', 'copy', 'heading', 'metadata', 'meta description', 'schema', 'faq', 'cta', 'call to action', 'mobile', 'responsive', 'content', 'local search', 'seo'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hasSeoEvidence(string $text): bool
    {
        $text = strtolower($text);

        foreach (['seo', 'search', 'metadata', 'meta description', 'title tag', 'schema', 'structured data', 'faq', 'answer engine', 'aeo', 'generative engine', 'geo', 'ai overview', 'ai search', 'aio', 'llm'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
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
    private function analysisModuleSummaries(Client $client, Collection $waivers): array
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
            ->map(function (array $module) use ($client, $waivers): array {
                $run = AnalysisRun::query()
                    ->where('client_id', $client->getKey())
                    ->where('module', $module['module'])
                    ->latest('started_at')
                    ->latest()
                    ->first();
                $stale = $run?->status === AnalysisRun::STATUS_COMPLETED
                    && $run->completed_at !== null
                    && $run->completed_at->lt(now()->subDays($this->analysisFreshnessDays()));
                $completed = $run?->status === AnalysisRun::STATUS_COMPLETED && ! $stale;
                $waiver = $completed ? null : $this->waiverForModule($waivers, (string) $module['module']);
                $waived = $waiver instanceof StandardAdvisoryPackWaiver;
                $state = match (true) {
                    $completed => 'complete',
                    $waived => 'waived',
                    $stale => 'stale',
                    $run?->status === AnalysisRun::STATUS_FAILED => 'failed',
                    ! $run instanceof AnalysisRun => 'missing',
                    default => $run->status,
                };

                return [
                    'module' => $module['module'],
                    'label' => $module['label'],
                    'status' => $state === 'complete' ? AnalysisRun::STATUS_COMPLETED : $state,
                    'state' => $state,
                    'raw_status' => $run?->status,
                    'completed' => $completed,
                    'stale' => $stale,
                    'waived' => $waived,
                    'ready_for_pack' => $completed || $waived,
                    'waivable' => ! $completed && ! $waived,
                    'waiver' => $waiver instanceof StandardAdvisoryPackWaiver ? $this->waiverPayload($waiver) : null,
                    'dropped_findings' => [
                        'missing_attribution' => (int) data_get($run?->metadata, 'dropped_findings.missing_attribution', 0),
                    ],
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
            'view_url' => route('advisor.reports.download', $report, absolute: false),
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

        $url = in_array($report['review_status'], ['not_required', 'reviewed'], true)
            ? route('portal.reports.show', $report['id'], absolute: false)
            : null;

        return [
            'id' => $report['id'],
            'type' => $report['type'],
            'type_label' => $report['type_label'],
            'title' => $report['title'],
            'generated_at' => $report['generated_at'],
            'review_status' => $report['review_status'],
            'reviewed_at' => $report['reviewed_at'],
            'view_url' => $url,
            'download_url' => $url,
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
        int $analysisWaived,
        bool $analysisReadyForPack,
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

        if ($analysisCompleted === 0 && $analysisWaived === 0) {
            return 'ready_for_analysis';
        }

        if (! $analysisReadyForPack) {
            return 'analysis_incomplete';
        }

        if ($analysisWaived > 0 && ($reports['client'] === null || $reports['advisor'] === null)) {
            return 'ready_for_pack_with_waiver';
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
        int $analysisWaived,
        bool $analysisReadyForPack,
        array $reports,
    ): string {
        return match ($this->status($response, $documents, $blockingVerifications, $analysisCompleted, $analysisWaived, $analysisReadyForPack, $reports)) {
            'waiting_questionnaire' => 'Waiting for questionnaire',
            'waiting_documents' => 'Waiting for evidence',
            'verification_blocked' => 'Evidence review needed',
            'ready_for_analysis' => 'Ready for analysis',
            'analysis_incomplete' => 'Analysis incomplete',
            'ready_for_pack_with_waiver' => 'Ready with waiver',
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
        int $analysisWaived,
        bool $analysisReadyForPack,
        array $reports,
    ): string {
        return match ($this->status($response, $documents, $blockingVerifications, $analysisCompleted, $analysisWaived, $analysisReadyForPack, $reports)) {
            'waiting_questionnaire' => 'Ask the client to complete onboarding.',
            'waiting_documents' => 'Ask the client to upload supporting evidence.',
            'verification_blocked' => 'Resolve document verification flags.',
            'ready_for_analysis' => 'Run Standard Advisory analysis.',
            'analysis_incomplete' => 'Complete every Standard Advisory analysis module before generating the pack.',
            'ready_for_pack_with_waiver' => 'Generate the advisory pack with the recorded partial-analysis waiver.',
            'ready_for_pack' => 'Generate the advisory pack.',
            'awaiting_report_release' => 'Review and release the client report.',
            default => 'Discuss the released report with the client.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $analysisModules
     */
    private function incompleteAnalysisModuleLabels(array $analysisModules): string
    {
        $labels = collect($analysisModules)
            ->reject(fn (array $module): bool => (bool) ($module['ready_for_pack'] ?? false))
            ->map(function (array $module): string {
                $status = (string) ($module['status'] ?? AnalysisRun::STATUS_QUEUED);

                return sprintf('%s (%s)', (string) ($module['label'] ?? 'Module'), str_replace('_', ' ', $status));
            })
            ->values()
            ->all();

        return implode(', ', $labels);
    }

    /**
     * @return Collection<int, StandardAdvisoryPackWaiver>
     */
    private function activePackWaivers(Client $client): Collection
    {
        return StandardAdvisoryPackWaiver::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->with('waivedBy')
            ->latest('waived_at')
            ->latest()
            ->get();
    }

    /**
     * @param  Collection<int, StandardAdvisoryPackWaiver>  $waivers
     */
    private function waiverForModule(Collection $waivers, string $module): ?StandardAdvisoryPackWaiver
    {
        return $waivers->first(
            fn (StandardAdvisoryPackWaiver $waiver): bool => $waiver->coversModule($module),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $analysisModules
     * @return array<int, string>
     */
    private function waivableModuleValues(array $analysisModules): array
    {
        return collect($analysisModules)
            ->filter(fn (array $module): bool => (bool) ($module['waivable'] ?? false))
            ->pluck('module')
            ->map(fn (mixed $module): string => (string) $module)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<int, string>
     */
    private function normaliseWaiverModules(array $modules): array
    {
        $allowed = $this->requiredAnalysisModuleValues();

        return collect($modules)
            ->map(fn (mixed $module): string => trim((string) $module))
            ->filter(fn (string $module): bool => in_array($module, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function waiverPayload(StandardAdvisoryPackWaiver $waiver): array
    {
        return [
            'id' => $waiver->id,
            'modules' => $waiver->modules ?? [],
            'reason' => $waiver->reason,
            'waived_at' => $waiver->waived_at?->toIso8601String(),
            'waived_by' => $waiver->waivedBy instanceof User
                ? [
                    'id' => $waiver->waivedBy->id,
                    'name' => $waiver->waivedBy->name,
                    'email' => $waiver->waivedBy->email,
                ]
                : null,
        ];
    }

    private function analysisFreshnessDays(): int
    {
        return max(1, (int) config('standard_advisory.analysis_freshness_days', 90));
    }

    private function analysisModuleLabel(AnalysisModule $module): string
    {
        return str($module->value)->replace('_', ' ')->title()->toString();
    }
}
