<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\NpoEngagement;
use App\Models\NpoValueCalculation;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\Scenario;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Dd\DataRoom;
use App\Services\Goals\GoalTracker;
use App\Services\Notifications\NotificationCenter;
use App\Services\Npo\NpoFunderMonitor;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoImpactMetricRecorder;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly OnboardingWizard $wizard,
        private readonly DataQualityScorer $dataQuality,
        private readonly NotificationCenter $notifications,
        private readonly GoalTracker $goals,
        private readonly BusinessHealthRadarBuilder $businessHealth,
        private readonly NpoHealthScorer $npoHealth,
        private readonly NpoFunderMonitor $npoFunding,
        private readonly NpoImpactMetricRecorder $npoImpactMetrics,
        private readonly StandardAdvisoryWorkflow $standardAdvisory,
    ) {}

    public function __invoke(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);
        $ddEngagement = $this->currentDdEngagement($client);
        $npoEngagement = $this->currentNpoEngagement($client);
        $postAcquisition = $this->currentPostAcquisitionMigration($client);
        $goals = $npoEngagement instanceof NpoEngagement
            ? $this->goals->dashboardForEngagement($client, $npoEngagement)
            : $this->goals->dashboard($client);

        return Inertia::render('portal/Dashboard', [
            'client' => $this->clientPayload($client),
            'progress' => $this->wizard->progress($client),
            'currentStep' => $this->wizard->currentStepSlug($client),
            'onboardingUrl' => route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ]),
            'notificationSummary' => $request->user() instanceof User
                ? $this->notifications->counts($request->user())
                : ['unread' => 0, 'urgent' => 0],
            'wellbeing' => $this->wellbeingPayload($client, $request->user()),
            'businessHealth' => $this->businessHealth->portalPayload($client),
            'healthFindings' => $this->businessHealth->healthFindingsPayload($client),
            'npoHealth' => $npoEngagement instanceof NpoEngagement ? $this->npoHealth->summary($npoEngagement) : null,
            'npoPortal' => $npoEngagement instanceof NpoEngagement ? $this->npoPortalPayload($client, $npoEngagement, $goals) : null,
            'ddPlan' => $ddEngagement instanceof DdEngagement ? $this->ddPlanPayload($ddEngagement) : null,
            'postAcquisition' => $postAcquisition instanceof PostAcquisitionMigration ? $this->postAcquisitionPayload($postAcquisition) : null,
            'standardAdvisory' => $this->standardAdvisory->portalSummary($client),
            'goals' => $goals,
            'documents' => $this->documentPayload($client, $npoEngagement),
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'npoImpactMetricStoreUrl' => $npoEngagement instanceof NpoEngagement ? route('portal.npo-impact-metrics.store', absolute: false) : null,
            'scenarios' => $this->scenarioPayload($client),
            'proposals' => $this->proposalPayload($client),
            'reports' => $this->reportPayload($client, $npoEngagement),
            'messagesUrl' => route('portal.messages.index', absolute: false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $dataQuality = $this->dataQuality->score($client);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'data_quality' => $dataQuality->level,
            'data_quality_summary' => $dataQuality->toPayload(),
            'nzbn' => $client->nzbn,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentPayload(Client $client, ?NpoEngagement $engagement = null): array
    {
        return Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->when($engagement instanceof NpoEngagement, fn ($query) => $query->where(function ($scope) use ($engagement): void {
                $scope->whereNull('npo_engagement_id')
                    ->orWhere('npo_engagement_id', $engagement->getKey());
            }))
            ->with('verifications')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'url' => route('portal.documents.show', $document, absolute: false),
                'verification_state' => $this->documentVerificationState($document),
                'client_explanation' => $this->documentClientExplanation($document),
                'verifications' => $document->verifications
                    ->map(fn (DocumentVerification $verification): array => [
                        'id' => $verification->id,
                        'outcome' => $verification->outcome,
                        'claim_text' => $verification->claim_text,
                        'client_explanation' => $verification->clientFacingExplanation(),
                        'resolved_at' => $verification->resolved_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function wellbeingPayload(Client $client, mixed $user): array
    {
        $current = $user instanceof User
            ? WellbeingCheckin::query()
                ->where('client_id', $client->getKey())
                ->where('user_id', $user->getKey())
                ->whereDate('period_start', now()->startOfMonth()->toDateString())
                ->latest('submitted_at')
                ->first()
            : null;

        return [
            'prompt_due' => ! $current instanceof WellbeingCheckin,
            'period_start' => now()->startOfMonth()->toDateString(),
            'submitted_at' => $current?->submitted_at?->toIso8601String(),
            'url' => route('portal.wellbeing.show'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioPayload(Client $client): array
    {
        return Scenario::query()
            ->where('client_id', $client->getKey())
            ->where('is_client_visible', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->sortBy('position')
            ->map(fn (Scenario $scenario): array => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'kind' => $scenario->kind,
                'pv_impact' => $scenario->pv_impact,
                'position' => $scenario->position,
                'economic_overlay' => [
                    'applied_growth_rate' => $scenario->economic_overlay['applied_growth_rate'] ?? null,
                    'discount_method' => $scenario->economic_overlay['discount_method'] ?? null,
                    'indicators' => $scenario->economic_overlay['indicators'] ?? [],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalPayload(Client $client): array
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->whereIn('status', ['released', 'awaiting_signature', 'signed'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'version' => $proposal->version,
                'status' => $proposal->status->value,
                'status_label' => str($proposal->status->value)->replace('_', ' ')->title()->toString(),
                'suggested_mid' => $proposal->feeCalculation?->suggested_mid,
                'signed_at' => $proposal->signed_at?->toIso8601String(),
                'signoff_url' => route('portal.proposals.signoff.show', $proposal, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportPayload(Client $client, ?NpoEngagement $engagement = null): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return Report::query()
            ->where('client_id', $client->getKey())
            ->when(
                $engagement instanceof NpoEngagement,
                fn ($query) => $query->where(function ($scope) use ($engagement): void {
                    $scope->where('type', ReportType::Client->value)
                        ->orWhere(function ($npoReports) use ($engagement): void {
                            $npoReports
                                ->where('npo_engagement_id', $engagement->getKey())
                                ->where(function ($reviewedScope): void {
                                    $reviewedScope
                                        ->where(function ($clientReady): void {
                                            $clientReady
                                                ->whereIn('type', [
                                                    ReportType::GovernanceReview->value,
                                                    ReportType::NpoHealth->value,
                                                    ReportType::SocialEnterpriseDual->value,
                                                ])
                                                ->whereIn('review_status', ['not_required', 'reviewed']);
                                        })
                                        ->orWhere(function ($reviewedReports): void {
                                            $reviewedReports
                                                ->whereIn('type', [
                                                    ReportType::FunderAccountability->value,
                                                    ReportType::ImpactSummary->value,
                                                ])
                                                ->where('review_status', 'reviewed');
                                        });
                                });
                        });
                }),
                fn ($query) => $query->where(function ($scope) use ($engagementType): void {
                    $scope
                        ->where('type', ReportType::Client->value)
                        ->whereIn('review_status', ['not_required', 'reviewed']);

                    if ($engagementType === EngagementType::POST_ACQUISITION_ADVISORY) {
                        $scope->orWhere(function ($postAcquisition): void {
                            $postAcquisition
                                ->where('type', ReportType::PostAcquisitionGap->value)
                                ->whereIn('review_status', ['not_required', 'reviewed']);
                        });
                    }

                    if ($engagementType === EngagementType::DUE_DILIGENCE) {
                        $scope->orWhere(function ($dueDiligence): void {
                            $dueDiligence
                                ->where('type', ReportType::DueDiligence->value)
                                ->whereIn('review_status', ['not_required', 'reviewed']);
                        });
                    }
                }),
            )
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'type' => $report->type->value,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'download_url' => route('portal.reports.show', $report, absolute: false),
            ])
            ->values()
            ->all();
    }

    private function currentNpoEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();
    }

    private function currentDdEngagement(Client $client): ?DdEngagement
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::DUE_DILIGENCE) {
            return null;
        }

        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    private function currentPostAcquisitionMigration(Client $client): ?PostAcquisitionMigration
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::POST_ACQUISITION_ADVISORY) {
            return null;
        }

        return PostAcquisitionMigration::query()
            ->where('advisory_client_id', $client->getKey())
            ->with([
                'ddReport',
                'engagement',
                'gapQuestionnaireResponse.answers',
                'gapQuestionnaireResponse.questionnaire.sections.questions',
                'proposal.feeCalculation',
            ])
            ->latest('migrated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function postAcquisitionPayload(PostAcquisitionMigration $migration): array
    {
        $response = $migration->gapQuestionnaireResponse;
        $questions = collect($response?->questionnaire?->sections ?? [])
            ->flatMap(fn ($section) => $section->questions)
            ->values();
        $totalQuestions = $questions?->count() ?? 0;
        $answeredQuestions = $response?->answers?->count() ?? 0;
        $remainingQuestionIds = data_get($migration->metadata, 'gap_questions_remaining');
        $submitted = $response instanceof QuestionnaireResponse && $response->submitted_at !== null;
        $remainingQuestions = $submitted
            ? 0
            : (is_array($remainingQuestionIds) ? count($remainingQuestionIds) : max(0, $totalQuestions - $answeredQuestions));
        $proposal = $migration->proposal;
        $proposalStatus = $proposal instanceof Proposal
            ? (is_string($proposal->status) ? $proposal->status : $proposal->status->value)
            : null;
        $proposalClientVisible = $proposal instanceof Proposal && in_array($proposalStatus, [
            'released',
            'awaiting_signature',
            'signed',
        ], true);

        return [
            'source_client_id' => $migration->buyer_client_id,
            'advisory_client_id' => $migration->advisory_client_id,
            'source_target_name' => $migration->engagement?->target_name,
            'dd_pv_baseline' => $migration->dd_pv_baseline,
            'migrated_at' => $migration->migrated_at?->toIso8601String(),
            'migrated_document_count' => count(is_array($migration->migrated_document_ids) ? $migration->migrated_document_ids : []),
            'gap_questionnaire_url' => route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE], absolute: false),
            'gap_questionnaire' => [
                'submitted' => $submitted,
                'submitted_at' => $response?->submitted_at?->toIso8601String(),
                'answered_questions' => $answeredQuestions,
                'total_questions' => $totalQuestions,
                'remaining_questions' => $remainingQuestions,
            ],
            'proposal' => $proposal instanceof Proposal ? [
                'id' => $proposal->id,
                'status' => $proposalStatus,
                'status_label' => str((string) $proposalStatus)->replace('_', ' ')->title()->toString(),
                'suggested_mid' => $proposal->feeCalculation?->suggested_mid,
                'client_visible' => $proposalClientVisible,
                'signoff_url' => $proposalClientVisible ? route('portal.proposals.signoff.show', $proposal, absolute: false) : null,
            ] : null,
            'dd_report' => $migration->ddReport instanceof Report ? [
                'id' => $migration->ddReport->id,
                'title' => $migration->ddReport->title,
                'generated_at' => $migration->ddReport->generated_at?->toIso8601String(),
            ] : null,
            'integration_actions' => $this->postAcquisitionIntegrationActions($migration),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function postAcquisitionIntegrationActions(PostAcquisitionMigration $migration): array
    {
        return DdIntegrationPlanItem::query()
            ->where('dd_engagement_id', $migration->dd_engagement_id)
            ->orderBy('day')
            ->limit(8)
            ->get()
            ->map(fn (DdIntegrationPlanItem $item): array => [
                'id' => $item->id,
                'day' => $item->day,
                'phase' => $item->phase,
                'action' => $item->action,
                'owner' => $item->owner,
                'priority' => $item->priority,
                'status' => $item->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ddPlanPayload(DdEngagement $engagement): array
    {
        $plan = BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->latest()
            ->first();

        return [
            'url' => route('portal.dd-plan.show', absolute: false),
            'generated' => $plan instanceof BusinessPlan,
            'status' => $plan?->status,
            'plan_completed' => $plan instanceof BusinessPlan && $plan->status === BusinessPlan::STATUS_FOUNDING,
            'business_advice_requested' => PostAcquisitionMigration::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->exists(),
            'updated_at' => $plan?->updated_at?->toIso8601String(),
            'target_name' => $engagement->target_name,
            'data_room_item_count' => $engagement->dataRoomItems()->count(),
            'workstream_options' => collect(DataRoom::WORKSTREAMS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $goals
     * @return array<string, mixed>
     */
    private function npoPortalPayload(Client $client, NpoEngagement $engagement, array $goals): array
    {
        $funding = $this->npoFunding->clientSummary($client, $engagement) ?? $this->emptyFundingPayload();
        $milestones = collect($goals['goals'] ?? [])
            ->flatMap(fn (array $goal): array => (array) ($goal['milestones'] ?? []))
            ->values();
        $completed = $milestones->where('status', 'completed')->count();
        $total = $milestones->count();
        $questionnaire = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('submitted_at')
            ->latest()
            ->first();

        return [
            'engagement_id' => $engagement->id,
            'sub_type' => $engagement->sub_type?->value,
            'legal_structure' => $engagement->legal_structure?->value,
            'funding' => $funding,
            'milestone_progress' => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'cost_per_beneficiary' => $this->costPerBeneficiaryPayload($engagement),
            ],
            'accountability_reports_due' => $this->accountabilityReportsDue($engagement),
            'impact_metrics' => $this->npoImpactMetrics->payloads($this->npoImpactMetrics->latest($engagement)),
            'questionnaire_completion' => [
                'completed' => $questionnaire instanceof QuestionnaireResponse && $questionnaire->submitted_at !== null,
                'submitted_at' => $questionnaire?->submitted_at?->toIso8601String(),
                'answered_questions' => $questionnaire instanceof QuestionnaireResponse ? $questionnaire->answers()->count() : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function costPerBeneficiaryPayload(NpoEngagement $engagement): ?array
    {
        $calculation = NpoValueCalculation::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('type', NpoValueCalculation::TYPE_COST_PER_BENEFICIARY)
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $calculation instanceof NpoValueCalculation) {
            return null;
        }

        return [
            'id' => $calculation->id,
            'cost_per_beneficiary' => $calculation->result['cost_per_beneficiary'] ?? null,
            'benchmark_cost_per_beneficiary' => $calculation->result['benchmark_cost_per_beneficiary'] ?? null,
            'additional_beneficiaries_mid' => $calculation->result['improvement']['additional_beneficiaries_mid'] ?? null,
            'rating' => $calculation->rating,
            'calculated_at' => $calculation->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountabilityReportsDue(NpoEngagement $engagement): array
    {
        return ClientFunderRecord::query()
            ->with('funder')
            ->where('npo_engagement_id', $engagement->getKey())
            ->whereNotNull('reporting_deadline')
            ->where('reporting_deadline', '<=', now()->addDays(60)->toDateString())
            ->orderBy('reporting_deadline')
            ->limit(6)
            ->get()
            ->map(fn (ClientFunderRecord $record): array => [
                'id' => $record->id,
                'funder_name' => $record->funder?->name,
                'grant_name' => $record->grant_name,
                'reporting_deadline' => $record->reporting_deadline?->toDateString(),
                'grant_amount' => (float) $record->grant_amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFundingPayload(): array
    {
        return [
            'summary' => [
                'active_records' => 0,
                'active_amount' => 0.0,
                'due_60_count' => 0,
                'expiry_alerts_count' => 0,
            ],
            'records' => [],
            'alerts' => [],
            'concentration' => [
                'total_active_amount' => 0.0,
                'largest_funder_amount' => 0.0,
                'largest_funder_ratio' => 0.0,
                'largest_funder_name' => null,
                'risk_level' => 'low',
                'source' => 'client_funder_records',
            ],
            'deadlines_60' => [],
        ];
    }

    private function documentVerificationState(Document $document): string
    {
        $outcomes = $document->verifications->pluck('outcome')->all();

        foreach ([
            DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            DocumentVerification::OUTCOME_ADVISORY_FLAG,
            DocumentVerification::OUTCOME_VERIFICATION_ERROR,
            DocumentVerification::OUTCOME_PENDING,
            DocumentVerification::OUTCOME_VERIFIED,
        ] as $outcome) {
            if (in_array($outcome, $outcomes, true)) {
                return $outcome;
            }
        }

        return DocumentVerification::OUTCOME_PENDING;
    }

    private function documentClientExplanation(Document $document): string
    {
        $verification = $document->verifications
            ->sortBy(fn (DocumentVerification $verification): int => match ($verification->outcome) {
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY => 0,
                DocumentVerification::OUTCOME_ADVISORY_FLAG => 1,
                DocumentVerification::OUTCOME_VERIFICATION_ERROR => 2,
                DocumentVerification::OUTCOME_PENDING => 3,
                default => 4,
            })
            ->first();

        return $verification instanceof DocumentVerification
            ? $verification->clientFacingExplanation()
            : 'Verification is in progress.';
    }
}
