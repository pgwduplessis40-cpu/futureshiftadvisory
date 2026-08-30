<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\PlanSection;
use App\Models\PostAcquisitionMigration;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Dd\ClientCapability;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Dd\PlanBuilder as DdPlanBuilder;
use App\Services\Dd\PostAcquisition;
use App\Services\Entrepreneurs\Guidance as EntrepreneurGuidance;
use App\Services\Pdf\ResilientPdfPreviewRenderer;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use App\Services\Portal\ServiceWorkspaces;
use App\Services\Questionnaires\QuestionnairePayload;
use App\Services\Questionnaires\QuestionnaireResponseRecorder;
use App\Services\Reports\BrandedReportLayout;
use App\Support\RequestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DdBusinessPlanController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly DdAdviceReportGenerator $reports,
        private readonly DdPlanBuilder $ddPlans,
        private readonly AcquisitionPlanRequirements $requirements,
        private readonly SharedPlanBuilder $plans,
        private readonly EntrepreneurGuidance $guidance,
        private readonly PostAcquisition $postAcquisition,
        private readonly ResilientPdfPreviewRenderer $pdf,
        private readonly BrandedReportLayout $layout,
        private readonly RequestContext $requestContext,
        private readonly ServiceWorkspaces $workspaces,
        private readonly QuestionnairePayload $questionnairePayloads,
        private readonly QuestionnaireResponseRecorder $questionnaireResponses,
        private readonly ClientCapability $clientCapability,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);

        if ($this->requiresOnboardingSupportLevel($client, $engagement)) {
            return to_route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_DD_SUPPORT,
            ]);
        }

        $readiness = $this->readiness($engagement);

        return Inertia::render('portal/dd/BusinessPlan', [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
                'engagement_type_label' => EngagementType::DUE_DILIGENCE->label(),
            ],
            'engagement' => [
                'id' => $engagement->id,
                'status' => $engagement->status,
                'target_name' => $engagement->target_name,
                'target_details' => $engagement->target_details ?? [],
            ],
            'readiness' => $readiness,
            'capability' => $this->capabilityPayload($engagement),
            'workspaces' => $this->workspaces->payload($client, ServiceWorkspaces::KEY_DUE_DILIGENCE),
            'questionnaire' => $this->questionnairePayload($client),
            'businessPlanBudgetUrl' => route('portal.business-plan-budget.show', ['client' => $client->id], absolute: false),
            'ddReportPdfUrl' => $this->clientVisibleDdReportUrl($engagement),
            'ddReportTitle' => $this->clientVisibleDdReport($engagement)?->title,
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'messagesUrl' => route('portal.messages.index', ['client' => $client->id], absolute: false),
            'workstreamOptions' => collect(DataRoom::WORKSTREAMS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ]);
    }

    private function clientVisibleDdReportUrl(DdEngagement $engagement): ?string
    {
        $report = $this->clientVisibleDdReport($engagement);

        return $report instanceof Report
            ? route('portal.reports.show', $report, absolute: false)
            : null;
    }

    private function clientVisibleDdReport(DdEngagement $engagement): ?Report
    {
        return Report::query()
            ->where('client_id', $engagement->client_id)
            ->whereIn('type', [
                ReportType::DueDiligence->value,
                ReportType::AcquisitionGoNoGo->value,
            ])
            ->whereIn('review_status', ['not_required', 'reviewed'])
            ->latest('generated_at')
            ->latest()
            ->get()
            ->first(fn (Report $report): bool => $this->reportMatchesEngagement($report, $engagement));
    }

    private function reportMatchesEngagement(Report $report, DdEngagement $engagement): bool
    {
        $engagementId = data_get($report->metadata, 'dd_engagement_id');

        return $engagementId === null || (string) $engagementId === (string) $engagement->getKey();
    }

    public function supportLevel(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'dd_experience' => ['required', 'string', Rule::in(['first_time', 'helped_before', 'completed_before'])],
            'business_ownership_experience' => ['required', 'string', Rule::in(['none', 'managed_business', 'owned_business', 'bought_or_sold_business'])],
            'financial_confidence' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'preferred_guidance' => ['required', 'string', Rule::in(['guided', 'balanced', 'fast_track'])],
        ]);

        $targetDetails = $engagement->target_details ?? [];
        $targetDetails['client_capability'] = $this->clientCapability->fromIntake(
            $validated,
            'dd_support_confirmation',
        );

        $engagement->forceFill(['target_details' => $targetDetails])->save();

        return to_route('portal.dd-plan.show', ['client' => $client->getKey()])
            ->with('status', 'dd-support-level-confirmed');
    }

    public function preview(Request $request): SymfonyResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $plan = $this->latestPlan($engagement);
        $readiness = $this->readiness($engagement);
        $phases = $plan instanceof BusinessPlan
            ? $this->planPayload($plan)['phases']
            : $this->templatePreviewPhases();

        $pdf = $this->pdf->render(
            $this->previewHtml($client, $engagement, $plan, $readiness, $phases),
            'Acquisition plan preview - '.($engagement->target_name ?: $client->trading_name ?: $client->legal_name),
        );
        $filename = Str::slug($engagement->target_name ?: 'acquisition-plan').'-business-plan-preview.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->reports->generateIfReady($engagement, $user);
        $this->ddPlans->buildFromWorkstreams($engagement, $user);

        return to_route('portal.dd-plan.show')->with('status', 'dd-acquisition-plan-generated');
    }

    public function questionnaire(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $questionnaire = $this->activeDdQuestionnaire();
        $user = $request->user();
        abort_unless($questionnaire instanceof Questionnaire && $user instanceof User, 404);

        $this->questionnaireResponses->record($client, $user, $questionnaire, $request->all());

        return back()->with('status', 'dd-questionnaire-submitted');
    }

    public function section(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $definitions = $this->requirements->definitions();
        $validated = $request->validate([
            'phase_key' => ['required', 'string', Rule::in(array_keys($definitions))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:80', 'max:8000'],
            'attached_document_ids' => ['array'],
            'attached_document_ids.*' => ['string', 'uuid'],
        ]);

        $phaseKey = (string) $validated['phase_key'];
        $requirementKey = (string) $validated['requirement_key'];
        $requirement = collect($definitions[$phaseKey]['requirements'])
            ->first(fn (array $definition): bool => $definition['key'] === $requirementKey);
        abort_unless(is_array($requirement), 422);

        $plan = $this->ddPlans->buildFromWorkstreams($engagement, $user);

        $this->plans->upsertSection(
            plan: $plan,
            phaseKey: $phaseKey,
            key: 'client-'.$phaseKey.'-'.$requirementKey,
            title: (string) ($validated['title'] ?: $requirement['title']),
            body: (string) $validated['body'],
            sourceType: BusinessPlan::SOURCE_DUE_DILIGENCE,
            metadata: [
                'source' => 'client_dd_plan_completion',
                'requirement_key' => $requirementKey,
                'requirement_title' => $requirement['title'],
                'dd_engagement_id' => $engagement->getKey(),
                'completed_by_user_id' => $user->getKey(),
            ],
            attachedDocumentIds: array_values((array) ($validated['attached_document_ids'] ?? [])),
        );

        return to_route('portal.dd-plan.show')->with('status', 'dd-plan-section-saved');
    }

    public function guidance(Request $request, PlanSection $planSection): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->assertSectionBelongsToEngagement($planSection, $engagement);

        $this->guidance->guide($planSection, $user);

        return to_route('portal.dd-plan.show')->with('status', 'dd-plan-guidance-generated');
    }

    public function complete(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $plan = $this->latestPlan($engagement) ?? $this->ddPlans->buildFromWorkstreams($engagement, $user);
        $requirementCompletion = $this->requirements->completion($plan);

        if (! $requirementCompletion['complete']) {
            return to_route('portal.dd-plan.show')
                ->with('status', 'dd-plan-requirements-missing')
                ->with('plan_missing_requirements', $requirementCompletion['missing']);
        }

        try {
            $this->ddPlans->markAcquisitionProceeding($engagement, $user);
        } catch (InvalidArgumentException $exception) {
            return to_route('portal.dd-plan.show')
                ->with('status', 'dd-plan-incomplete')
                ->with('plan_completion_error', $exception->getMessage());
        }

        return to_route('portal.dd-plan.show')->with('status', 'dd-plan-completed');
    }

    public function requestAdvice(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $engagement = $this->engagementFor($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $plan = $this->latestPlan($engagement);
        $readiness = $this->readiness($engagement);
        $businessAdvice = $this->businessAdvicePayload($engagement, $plan, $readiness);

        if (! $businessAdvice['available']) {
            return to_route('portal.dd-plan.show')
                ->with('status', 'dd-business-advice-not-ready')
                ->with('business_advice_blockers', $businessAdvice['blockers']);
        }

        $role = $user->fsaRole();
        $clientIds = $user->accessibleClientIds();

        try {
            $this->requestContext->apply('system', $clientIds, (string) $user->getKey());
            $this->postAcquisition->convert($engagement, $user);
        } finally {
            $this->requestContext->apply($role, $clientIds, (string) $user->getKey());
        }

        return to_route('portal.dashboard')->with('status', 'dd-business-advice-requested');
    }

    private function engagementFor(Client $client): DdEngagement
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType === EngagementType::DUE_DILIGENCE) {
            return DdEngagement::query()
                ->where('client_id', $client->getKey())
                ->latest()
                ->firstOrFail();
        }

        $activation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereNotNull('related_dd_engagement_id')
            ->latest()
            ->first();

        abort_unless($activation instanceof ServiceActivation, 404);

        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereKey($activation->related_dd_engagement_id)
            ->firstOrFail();
    }

    private function latestPlan(DdEngagement $engagement): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->with('phases.sections')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function readiness(DdEngagement $engagement): array
    {
        $questionnaire = QuestionnaireResponse::query()
            ->where('client_id', $engagement->client_id)
            ->whereNotNull('submitted_at')
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::DUE_DILIGENCE))
            ->latest('submitted_at')
            ->first();
        $dataRoomItemCount = $engagement->dataRoomItems()->count();
        $valuation = $engagement->valuations()->latest('as_at')->latest()->first();
        $workstreamTotal = count(DataRoom::WORKSTREAMS);
        $workstreamCompleted = $engagement->workstreams()
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count();
        $report = Report::query()
            ->where('client_id', $engagement->client_id)
            ->where('type', ReportType::DueDiligence)
            ->latest('generated_at')
            ->get()
            ->first(fn (Report $report): bool => (string) data_get($report->metadata, 'dd_engagement_id') === (string) $engagement->getKey());
        $missing = [];

        if (! $questionnaire instanceof QuestionnaireResponse) {
            $missing[] = 'Submit the DD questionnaire';
        }

        if ($dataRoomItemCount === 0) {
            $missing[] = 'Upload DD evidence';
        }

        if (! $valuation instanceof DdValuation) {
            $missing[] = 'Provide valuation financials';
        }

        if ($workstreamCompleted < $workstreamTotal) {
            $missing[] = 'Complete DD workstream analysis';
        }

        return [
            'questionnaire_submitted' => $questionnaire instanceof QuestionnaireResponse,
            'questionnaire_submitted_at' => $questionnaire?->submitted_at?->toIso8601String(),
            'data_room_item_count' => $dataRoomItemCount,
            'valuation_ready' => $valuation instanceof DdValuation,
            'valuation_as_at' => $valuation?->as_at?->toIso8601String(),
            'workstreams_completed' => $workstreamCompleted,
            'workstreams_total' => $workstreamTotal,
            'advice_report_ready' => $report instanceof Report,
            'advice_report_generated_at' => $report?->generated_at?->toIso8601String(),
            'missing' => $missing,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function questionnairePayload(Client $client): ?array
    {
        $questionnaire = $this->activeDdQuestionnaire();

        if (! $questionnaire instanceof Questionnaire) {
            return null;
        }

        $response = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->where('questionnaire_id', $questionnaire->getKey())
            ->with('answers')
            ->latest('submitted_at')
            ->latest()
            ->first();

        return [
            'schema' => $this->questionnairePayloads->schema($questionnaire),
            'answers' => $this->questionnairePayloads->answers($response),
            'submitUrl' => route('portal.dd-plan.questionnaire.store', ['client' => $client->id], absolute: false),
            'submitted' => $response instanceof QuestionnaireResponse && $response->submitted_at !== null,
            'submittedAt' => $response?->submitted_at?->toIso8601String(),
        ];
    }

    private function activeDdQuestionnaire(): ?Questionnaire
    {
        return Questionnaire::query()
            ->published()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->with('sections.questions')
            ->latest('published_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilityPayload(DdEngagement $engagement): array
    {
        $capability = (array) data_get($engagement->target_details ?? [], 'client_capability', []);
        $mode = in_array(($capability['mode'] ?? null), ['guided', 'experienced'], true)
            ? (string) $capability['mode']
            : 'guided';

        return [
            'mode' => $mode,
            'label' => $mode === 'experienced' ? 'Experienced DD support' : 'Guided DD support',
            'summary' => $mode === 'experienced'
                ? 'This workspace uses a shorter path for buyers who already understand business ownership, financials, or DD.'
                : 'This workspace should keep the next step plain, visible, and guided for a first-time or less confident buyer.',
            'next_step_style' => $mode === 'experienced' ? 'Compact prompts' : 'Step-by-step prompts',
            'dd_experience' => $capability['dd_experience'] ?? null,
            'business_ownership_experience' => $capability['business_ownership_experience'] ?? null,
            'financial_confidence' => $capability['financial_confidence'] ?? null,
            'preferred_guidance' => $capability['preferred_guidance'] ?? null,
            'requires_confirmation' => $this->clientCapability->needsConfirmation($capability),
            'confirm_url' => route('portal.dd-plan.support-level.store', ['client' => $engagement->client_id], absolute: false),
        ];
    }

    private function requiresOnboardingSupportLevel(Client $client, DdEngagement $engagement): bool
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::DUE_DILIGENCE) {
            return false;
        }

        $capability = (array) data_get($engagement->target_details ?? [], 'client_capability', []);

        return $this->clientCapability->needsConfirmation($capability);
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');
        $definitions = $this->requirements->definitions();
        $phasesByKey = $plan->phases->keyBy('key');
        $requirements = $this->requirements->payload($plan);
        $requirementCompletion = $this->requirements->completion($plan, $requirements);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'completed_at' => $plan->completed_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'completion' => $this->plans->completion($plan),
            'requirements_complete' => $requirementCompletion['complete'],
            'missing_requirements' => $requirementCompletion['missing'],
            'phases' => collect($definitions)
                ->map(function (array $definition, string $phaseKey) use ($phasesByKey, $requirements): array {
                    $phase = $phasesByKey->get($phaseKey);

                    return [
                        'id' => (string) ($phase?->id ?? $phaseKey),
                        'key' => $phaseKey,
                        'title' => (string) ($phase?->title ?? $definition['title']),
                        'status' => (string) ($phase?->status ?? 'pending'),
                        'requirements' => $requirements[$phaseKey] ?? [],
                        'sections' => $phase?->sections
                            ->sortBy('created_at')
                            ->map(fn ($section): array => [
                                'id' => $section->id,
                                'title' => $section->title,
                                'body' => $section->body,
                                'source_type' => $section->source_type,
                                'completeness_status' => $section->completeness_status,
                                'attached_document_ids' => $section->attached_document_ids ?? [],
                                'predictive_score' => $section->predictive_score,
                                'guidance' => data_get($section->metadata, 'ai_guidance'),
                                'requirement_key' => data_get($section->metadata, 'requirement_key'),
                                'guidance_url' => route('portal.dd-plan.sections.guidance', $section, absolute: false),
                            ])
                            ->values()
                            ->all() ?? [],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templatePreviewPhases(): array
    {
        return collect($this->requirements->templatePayload())
            ->map(fn (array $phase): array => [
                'id' => $phase['key'],
                'key' => $phase['key'],
                'title' => $phase['title'],
                'status' => 'pending',
                'requirements' => $phase['requirements'],
                'sections' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function previewHtml(
        Client $client,
        DdEngagement $engagement,
        ?BusinessPlan $plan,
        array $readiness,
        array $phases,
    ): string {
        $requirements = collect($phases)->flatMap(fn (array $phase): array => $phase['requirements'] ?? []);
        $totalRequirements = $requirements->count();
        $completedRequirements = $requirements
            ->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->count();
        $missingRequirements = $requirements
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => ($requirement['phase_title'] ?? 'Plan').': '.$requirement['title'])
            ->values()
            ->all();
        $phaseHtml = collect($phases)
            ->map(fn (array $phase): string => $this->previewPhaseHtml($phase))
            ->implode('');
        $readinessMissing = collect($readiness['missing'])
            ->map(fn (string $item): string => '<li>'.$this->escape($item).'</li>')
            ->implode('');
        $clientName = $client->trading_name ?: $client->legal_name;
        $planStatus = $plan instanceof BusinessPlan ? $plan->status : 'not populated';
        $generatedAt = now()->format('M j, Y g:i A');

        return $this->layout->document(
            title: 'Business plan preview - '.$engagement->target_name,
            templateKey: 'business-plan-preview',
            documentTag: 'Business plan preview',
            eyebrow: 'Due diligence acquisition plan',
            heading: 'Business plan preview',
            subheading: $engagement->target_name.' / '.$clientName,
            meta: [
                'Plan status' => $this->formatPreviewLabel($planStatus),
                'Requirements' => "{$completedRequirements}/{$totalRequirements} complete",
                'DD evidence' => $readiness['data_room_item_count'].' uploaded',
                'Advice report' => (bool) $readiness['advice_report_ready'] ? 'Ready' : 'Pending',
            ],
            contentHtml: $this->previewMissingHtml($missingRequirements, $readinessMissing).$phaseHtml,
            footer: 'Generated '.$generatedAt.' using Future Shift Advisory business plan preview',
            metaColumns: 4,
        );
    }

    /**
     * @param  array<int, string>  $missingRequirements
     */
    private function previewMissingHtml(array $missingRequirements, string $readinessMissing): string
    {
        $items = collect($missingRequirements)
            ->map(fn (string $item): string => '<li>'.$this->escape($item).'</li>')
            ->implode('');

        if ($items === '' && $readinessMissing === '') {
            return '';
        }

        return sprintf(
            '<article class="report-section missing-panel"><h2>Open items before finalising</h2><ul>%s%s</ul></article>',
            $items,
            $readinessMissing,
        );
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function previewPhaseHtml(array $phase): string
    {
        $sections = collect($phase['sections'] ?? []);
        $requirements = collect($phase['requirements'] ?? []);
        $matchedSectionIds = $requirements
            ->pluck('section_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $requirementHtml = $requirements
            ->map(fn (array $requirement): string => $this->previewRequirementHtml($requirement, $sections))
            ->implode('');
        $additionalHtml = $sections
            ->reject(fn (array $section): bool => in_array((string) $section['id'], $matchedSectionIds, true))
            ->map(fn (array $section): string => $this->previewAdditionalSectionHtml($section))
            ->implode('');

        return sprintf(
            '<article class="report-section"><h2>%s</h2>%s%s</article>',
            $this->escape((string) $phase['title']),
            $requirementHtml,
            $additionalHtml,
        );
    }

    private function previewRequirementHtml(array $requirement, $sections): string
    {
        $section = null;
        $sectionId = $requirement['section_id'] ?? null;

        if ($sectionId !== null) {
            $section = $sections->first(fn (array $candidate): bool => (string) $candidate['id'] === (string) $sectionId);
        }

        if ($section === null) {
            $section = $sections->first(
                fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key'],
            );
        }

        $complete = (bool) ($requirement['complete'] ?? false);
        $statusClass = $complete ? 'complete' : 'pending';
        $statusLabel = $complete ? 'Complete' : 'Pending';
        $content = is_array($section)
            ? $this->previewSectionContentHtml($section)
            : '<p class="body">Pending input: '.$this->escape((string) $requirement['description']).'</p>';

        return sprintf(
            '<article class="requirement"><h3>%s <span class="status %s">%s</span></h3>%s</article>',
            $this->escape((string) $requirement['title']),
            $statusClass,
            $statusLabel,
            $content,
        );
    }

    private function previewAdditionalSectionHtml(array $section): string
    {
        return sprintf(
            '<article class="requirement"><h3>%s <span class="status complete">DD insight</span></h3>%s</article>',
            $this->escape((string) $section['title']),
            $this->previewSectionContentHtml($section),
        );
    }

    private function previewSectionContentHtml(array $section): string
    {
        $documentCount = count((array) ($section['attached_document_ids'] ?? []));
        $source = $this->formatPreviewLabel((string) ($section['source_type'] ?? 'client input'));
        $docs = $documentCount === 1 ? '1 supporting document' : "{$documentCount} supporting documents";

        return sprintf(
            '<div class="body">%s</div><p class="note">Source: %s. Evidence: %s.</p>',
            nl2br($this->escape((string) ($section['body'] ?? ''))),
            $this->escape($source),
            $this->escape($docs),
        );
    }

    private function formatPreviewLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function assertSectionBelongsToEngagement(PlanSection $section, DdEngagement $engagement): void
    {
        $section->loadMissing('businessPlan');
        $plan = $section->businessPlan;

        abort_unless(
            $plan instanceof BusinessPlan
            && $plan->source_type === BusinessPlan::SOURCE_DUE_DILIGENCE
            && (string) $plan->dd_engagement_id === (string) $engagement->getKey(),
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function businessAdvicePayload(DdEngagement $engagement, ?BusinessPlan $plan, array $readiness): array
    {
        $migration = PostAcquisitionMigration::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->with('advisoryClient')
            ->first();
        $planReady = $plan instanceof BusinessPlan
            && $plan->status === BusinessPlan::STATUS_FOUNDING
            && $plan->completed_at !== null
            && $engagement->status === DdEngagement::STATUS_ACQUISITION_PROCEEDING;
        $blockers = [];

        if (! (bool) $readiness['advice_report_ready']) {
            $blockers[] = 'Advisor DD advice report is not ready yet.';
        }

        if (! $planReady) {
            $blockers[] = 'Complete the acquisition business plan first.';
        }

        return [
            'requested' => $migration instanceof PostAcquisitionMigration,
            'available' => ! ($migration instanceof PostAcquisitionMigration) && $blockers === [],
            'blockers' => $migration instanceof PostAcquisitionMigration ? [] : $blockers,
            'requestUrl' => route('portal.dd-plan.business-advice.store', absolute: false),
            'dashboardUrl' => route('portal.dashboard', absolute: false),
            'advisory_client' => $migration instanceof PostAcquisitionMigration ? [
                'id' => $migration->advisoryClient?->id,
                'legal_name' => $migration->advisoryClient?->legal_name,
                'engagement_type' => $migration->advisoryClient?->engagement_type?->value ?? (string) $migration->advisoryClient?->engagement_type,
            ] : null,
        ];
    }
}
