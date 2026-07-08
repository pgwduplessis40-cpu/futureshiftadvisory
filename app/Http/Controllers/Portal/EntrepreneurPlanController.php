<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\InviteToken;
use App\Models\MessageThread;
use App\Models\PlanSection;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Services\Entrepreneurs\Readiness;
use App\Services\Messaging\MessageThreadService;
use App\Services\Pdf\PdfRenderer;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use App\Services\Reports\BrandedReportLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class EntrepreneurPlanController extends Controller
{
    private const ADVISORY_REQUEST_SUBJECT = 'Advisory conversion request';

    private const GAMIFICATION_DISABLE_REQUEST_SUBJECT = 'Gamification disable request';

    private const BUDGET_UNLOCK_REQUIREMENT_KEY = 'business-type-location';

    private const BUDGET_ASSUMPTIONS_REQUIREMENT_KEY = 'financial-assumptions';

    private const READINESS_FIELDS = [
        'concept_clarity' => 'Concept clarity',
        'customer_need' => 'Customer need',
        'evidence_strength' => 'Evidence strength',
        'industry_experience' => 'Industry experience',
        'personal_capacity' => 'Personal capacity',
        'financial_runway' => 'Financial runway',
        'support_network' => 'Support network',
        'launch_readiness' => 'Launch readiness',
    ];

    public function __construct(
        private readonly Readiness $readiness,
        private readonly IdeaValidationService $ideas,
        private readonly PlanBuilder $plans,
        private readonly SharedPlanBuilder $sharedPlans,
        private readonly Guidance $guidance,
        private readonly PlanDocuments $documents,
        private readonly MessageThreadService $messages,
        private readonly PdfRenderer $pdf,
        private readonly BrandedReportLayout $layout,
        private readonly BudgetPackBuilder $budgetPack,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurBudgetService $budgets,
        private readonly EntrepreneurMilestones $milestones,
        private readonly EntrepreneurGamification $gamification,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
    ) {}

    public function show(Request $request): Response
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);
        $packageAccess = $this->packageAccessPayload($profile);

        return Inertia::render('portal/entrepreneur/Plan', [
            'profile' => $this->profilePayload($profile),
            'packageAccess' => $packageAccess,
            'readiness' => $this->readinessPayload($profile),
            'readinessFields' => collect(self::READINESS_FIELDS)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'ideaValidation' => $this->ideaValidationPayload($profile),
            'plan' => $plan instanceof BusinessPlan ? $this->planPayload($plan) : null,
            'planTemplate' => $this->planTemplatePayload(),
            'reports' => $this->reportPayloads($profile),
            'advisoryRequest' => $this->advisoryRequestPayload($profile, $plan),
            'gamification' => $this->gamificationPayload($profile, $plan),
            'urls' => [
                'dashboard' => route('portal.entrepreneur.dashboard', absolute: false),
                'readiness' => route('portal.entrepreneur.readiness.store', absolute: false),
                'ideaValidation' => route('portal.entrepreneur.idea-validation.store', absolute: false),
                'startPlan' => route('portal.entrepreneur.plan.start', absolute: false),
                'sectionStore' => route('portal.entrepreneur.plan.sections.store', absolute: false),
                'budgetUpdate' => route('portal.entrepreneur.plan.budget.update', absolute: false),
                'budgetPack' => route('portal.entrepreneur.plan.budget-pack.show', absolute: false),
                'budgetPackPdf' => route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false),
                'budgetFlagAcknowledge' => route('portal.entrepreneur.plan.budget.flags.acknowledge', absolute: false),
                'budgetAdvisorNudgeDismiss' => route('portal.entrepreneur.plan.budget.advisor-nudge.dismiss', absolute: false),
                'assistRequirement' => route('portal.entrepreneur.plan.requirements.assist', absolute: false),
                'preview' => route('portal.entrepreneur.plan.preview', absolute: false),
                'submit' => route('portal.entrepreneur.plan.submit', absolute: false),
                'documentUpload' => route('portal.documents.store', absolute: false),
                'messages' => route('portal.messages.index', absolute: false),
                'advisoryRequest' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            ],
        ]);
    }

    public function preview(Request $request): SymfonyResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        abort_unless($this->packageIncludesPlanBudget($profile), 403);
        $plan = $this->latestPlan($profile);
        $phases = $plan instanceof BusinessPlan
            ? $this->planPayload($plan)['phases']
            : $this->templatePreviewPhases();

        $pdf = $this->pdf->render($this->previewHtml($profile, $plan, $phases));
        $filename = Str::slug($profile->name ?: 'entrepreneur-business-plan').'-preview.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function budgetPack(Request $request): Response|RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        abort_unless($this->packageIncludesPlanBudget($profile), 403);
        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        if (! $this->budgetUnlocked($plan)) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-budget-locked')
                ->with('entrepreneur_plan_error', 'Complete Foundation: Business type, location, and operating model, plus Financial: Financial assumptions before viewing the budget pack.');
        }

        return Inertia::render('portal/entrepreneur/BudgetPack', [
            'pack' => $this->budgetPack->payload($profile, $plan),
            'urls' => [
                'plan' => route('portal.entrepreneur.plan.show', absolute: false),
                'pdf' => route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false),
            ],
        ]);
    }

    public function budgetPackPdf(Request $request): SymfonyResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        abort_unless($this->packageIncludesPlanBudget($profile), 403);
        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan && $this->budgetUnlocked($plan), 404);

        $pdf = $this->pdf->render($this->budgetPack->html($profile, $plan));
        $filename = Str::slug($profile->name ?: 'entrepreneur').'-budget-pack.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function readiness(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);

        $rules = collect(array_keys(self::READINESS_FIELDS))
            ->mapWithKeys(fn (string $field): array => [$field => ['required', 'numeric', 'min:0', 'max:5']])
            ->all();
        $validated = $request->validate([
            ...$rules,
            'personal_barriers' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->readiness->assess($profile, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-readiness-saved');
    }

    public function ideaValidation(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesIdeaValidation($profile)) {
            return $this->packageLockedResponse('Idea validation is not included in your selected package.');
        }

        $validated = $request->validate([
            'problem' => ['required', 'string', 'min:20', 'max:1200'],
            'target_customer' => ['required', 'string', 'min:20', 'max:1200'],
            'solution' => ['required', 'string', 'min:20', 'max:1200'],
            'value_proposition' => ['required', 'string', 'min:20', 'max:1200'],
            'demand_signal' => ['required', 'string', 'min:20', 'max:1200'],
            'revenue_model' => ['required', 'string', 'min:20', 'max:1200'],
        ]);

        $this->ideas->evaluate($profile, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-idea-submitted');
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);

        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Business plan and budget are not included in your selected package.');
        }

        if (! $this->packageIncludesIdeaValidation($profile)) {
            $plan = $this->sharedPlans->createOrUpdateForEntrepreneur($profile, [
                'title' => 'Business plan: '.$profile->name,
                'status' => BusinessPlan::STATUS_BUILDING,
                'current_phase' => 1,
            ], $user);

            if ($profile->client_id !== null) {
                $plan->forceFill(['client_id' => $profile->client_id])->save();
            }
            $profile->forceFill(['stage' => EntrepreneurStage::BUILDING_PHASE_1])->save();

            $this->audit->record('entrepreneur.plan_started', subject: $plan, actor: $user, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            ]);

            return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-started');
        }

        try {
            $plan = $this->plans->start($profile, $user);
            if ($profile->client_id !== null) {
                $plan->forceFill(['client_id' => $profile->client_id])->save();
            }
        } catch (InvalidArgumentException $exception) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-plan-locked')
                ->with('entrepreneur_plan_error', $exception->getMessage());
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-started');
    }

    public function section(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Business plan and budget are not included in your selected package.');
        }

        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $validated = $request->validate([
            'phase_key' => ['required', 'string', Rule::in(array_keys(PlanRequirements::definitions()))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:80', 'max:8000'],
            'attached_document_ids' => ['array'],
            'attached_document_ids.*' => ['string', 'uuid'],
        ]);

        $phaseKey = (string) $validated['phase_key'];
        $requirementKey = (string) $validated['requirement_key'];
        $requirement = $this->requirement($phaseKey, $requirementKey);

        $section = $this->plans->upsertSection(
            plan: $plan,
            phaseKey: $phaseKey,
            key: 'founder-'.$phaseKey.'-'.$requirementKey,
            title: (string) ($validated['title'] ?: $requirement['title']),
            body: (string) $validated['body'],
            actor: $user,
            metadata: [
                'source' => 'entrepreneur_plan_workspace',
                'requirement_key' => $requirementKey,
                'requirement_title' => $requirement['title'],
                'completed_by_user_id' => $user->getKey(),
            ],
        );

        foreach (array_values((array) ($validated['attached_document_ids'] ?? [])) as $documentId) {
            $document = Document::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->whereKey($documentId)
                ->first();

            if ($document instanceof Document) {
                $this->documents->attachAndVerify(
                    section: $section,
                    document: $document,
                    actor: $user,
                    claim: Str::limit((string) $validated['body'], 500, ''),
                );
            }
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-section-saved');
    }

    public function budget(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        if (! $this->budgetUnlocked($plan)) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-budget-locked')
                ->with('entrepreneur_plan_error', 'Complete Foundation: Business type, location, and operating model, plus Financial: Financial assumptions before setting up the budget.');
        }

        $validated = $request->validate([
            'expected_runway_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'forecast_years' => ['nullable', 'integer', Rule::in([1, 2, 3, 5])],
            'assumptions' => ['array'],
            'assumptions.revenue_growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'assumptions.cost_inflation_percent' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'assumptions.target_gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_before_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_after_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'launch_costs' => ['array', 'max:50'],
            'launch_costs.*.label' => ['nullable', 'string', 'max:180'],
            'launch_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'launch_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'launch_costs.*.month' => ['nullable', 'integer', 'min:1', 'max:60'],
            'launch_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'monthly_fixed_costs' => ['array', 'max:50'],
            'monthly_fixed_costs.*.label' => ['nullable', 'string', 'max:180'],
            'monthly_fixed_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'monthly_fixed_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'monthly_fixed_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'revenue_forecast' => ['array', 'max:50'],
            'revenue_forecast.*.label' => ['nullable', 'string', 'max:180'],
            'revenue_forecast.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'revenue_forecast.*.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'revenue_forecast.*.monthly_growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'revenue_forecast.*.variable_cost_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_sources' => ['array', 'max:50'],
            'funding_sources.*.label' => ['nullable', 'string', 'max:180'],
            'funding_sources.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_sources.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'funding_sources.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'future_costs' => ['array', 'max:50'],
            'future_costs.*.label' => ['nullable', 'string', 'max:180'],
            'future_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'future_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'future_costs.*.year' => ['nullable', 'integer', 'min:2', 'max:5'],
            'future_costs.*.recurring' => ['nullable', 'boolean'],
            'future_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_scenarios' => ['array', 'max:10'],
            'funding_scenarios.*.name' => ['nullable', 'string', 'max:180'],
            'funding_scenarios.*.type' => ['nullable', 'string', Rule::in(['bank_loan', 'investor', 'mixed'])],
            'funding_scenarios.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_scenarios.*.year' => ['nullable', 'integer', 'min:1', 'max:5'],
            'funding_scenarios.*.interest_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.term_years' => ['nullable', 'integer', 'min:0', 'max:30'],
            'funding_scenarios.*.interest_only_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'funding_scenarios.*.investor_equity_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
        ]);

        $this->budgets->update($plan, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-saved');
    }

    public function acknowledgeBudgetFlag(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80'],
        ]);
        $budget = $plan->budgetRunway()->first();
        abort_unless($budget instanceof EntrepreneurBudget, 404);

        $this->budgets->acknowledgeFlag($budget, (string) $validated['key'], $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-flag-acknowledged');
    }

    public function dismissBudgetAdvisorNudge(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Budget setup is not included in your selected package.');
        }

        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $budget = $plan->budgetRunway()->first();
        abort_unless($budget instanceof EntrepreneurBudget, 404);

        $this->budgets->dismissAdvisorLineNudge($budget, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-budget-advisor-nudge-dismissed');
    }

    public function assistRequirement(Request $request): JsonResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        abort_unless($this->packageIncludesPlanBudget($profile), 403);
        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $validated = $request->validate([
            'phase_key' => ['required', 'string', Rule::in(array_keys(PlanRequirements::definitions()))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'body' => ['nullable', 'string', 'max:8000'],
        ]);
        $phaseKey = (string) $validated['phase_key'];
        $requirement = [
            ...$this->requirement($phaseKey, (string) $validated['requirement_key']),
            'phase_key' => $phaseKey,
            'phase_title' => PlanRequirements::phaseTitle($phaseKey),
        ];

        return response()->json($this->guidance->draftRequirement(
            plan: $plan,
            profile: $profile,
            requirement: $requirement,
            ideaValidation: $this->latestIdeaValidation($profile),
            currentDraft: (string) ($validated['body'] ?? ''),
            actor: $user,
        ));
    }

    public function guidance(Request $request, PlanSection $planSection): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        abort_unless($this->packageIncludesPlanBudget($profile), 403);
        $this->assertSectionBelongsToProfile($planSection, $profile);

        $this->guidance->guide($planSection, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-guidance-generated');
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Business plan assessment is not included in your selected package.');
        }

        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $completion = $this->requirementsCompletion($plan);
        if (! $completion['complete']) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-plan-requirements-missing')
                ->with('entrepreneur_plan_missing_requirements', $completion['missing']);
        }

        $plan->forceFill([
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => $plan->submitted_at ?? now(),
            'founding_advisory_payload' => $this->sharedPlans->foundingPayload($plan),
        ])->save();
        $profile->forceFill(['stage' => EntrepreneurStage::SUBMITTED])->save();

        $this->audit->record('entrepreneur.plan_submitted', subject: $plan, actor: $user, after: [
            'entrepreneur_profile_id' => $profile->getKey(),
            'completed_requirements' => $completion['completed'],
        ]);
        $this->milestones->awardPlanSubmitted($plan->refresh()->load('entrepreneurProfile'));

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-submitted');
    }

    public function requestAdvisory(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        if (! $this->packageIncludesPlanBudget($profile)) {
            return $this->packageLockedResponse('Advisory conversion is available after a business plan and budget package.');
        }

        $plan = $this->latestPlan($profile);
        $signal = $this->latestReadinessSignal($profile);

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-not-ready');
        }

        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::ADVISORY_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        if (! $thread instanceof MessageThread) {
            $message = $this->messages->startEntrepreneurThread(
                profile: $profile,
                sender: $user,
                subject: self::ADVISORY_REQUEST_SUBJECT,
                body: sprintf(
                    'I would like to request standard advisory support using my entrepreneur business plan%s.',
                    $plan instanceof BusinessPlan ? ' ('.$plan->title.')' : '',
                ),
            );
            $thread = $message->thread;
        }

        $this->audit->record('entrepreneur.advisory_requested', subject: $profile, actor: $user, after: [
            'business_plan_id' => $plan?->getKey(),
            'advisory_readiness_signal_id' => $signal->getKey(),
            'message_thread_id' => $thread?->getKey(),
        ]);

        return $thread instanceof MessageThread
            ? to_route('portal.messages.show', $thread)->with('status', 'entrepreneur-advisory-requested')
            : to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-requested');
    }

    private function entrepreneurUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(
            $user->user_type === User::TYPE_ENTREPRENEUR
            || ($this->activeEntrepreneurActivationForUser($user) instanceof ServiceActivation),
            403,
        );

        return $user;
    }

    private function profileFor(User $user): EntrepreneurProfile
    {
        $this->entrepreneurInvites->reconcile($user);
        $activation = $this->activeEntrepreneurActivationForUser($user);

        if ($activation instanceof ServiceActivation && $activation->related_entrepreneur_profile_id !== null) {
            return EntrepreneurProfile::query()
                ->whereKey($activation->related_entrepreneur_profile_id)
                ->firstOrFail();
        }

        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    private function activeEntrepreneurActivationForUser(User $user): ?ServiceActivation
    {
        $clientIds = $user->accessibleClientIds();

        if ($clientIds === []) {
            return null;
        }

        return ServiceActivation::query()
            ->whereIn('client_id', $clientIds)
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereNotNull('related_entrepreneur_profile_id')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function packageAccessPayload(EntrepreneurProfile $profile): array
    {
        $activation = $this->activeEntrepreneurActivationForProfile($profile);
        $snapshot = (array) ($activation?->selected_package_snapshot ?? []);
        $profile->loadMissing('inviteToken');
        $invite = $profile->inviteToken;
        $inviteScope = $invite instanceof InviteToken
            && $invite->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            ? $invite->intended_package_scope
            : null;
        $scope = $activation instanceof ServiceActivation
            ? (string) ($snapshot['package_scope'] ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO)
            : (string) ($inviteScope ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO);
        $access = ServiceRatePackage::accessFor(
            ServiceRatePackage::SERVICE_ENTREPRENEUR,
            $scope,
        );

        return [
            ...$access,
            'package_label' => (string) ($snapshot['client_label'] ?? $snapshot['package_name'] ?? $invite?->serviceIntentLabel() ?? 'Entrepreneur workspace'),
            'source_activation_id' => $activation?->getKey(),
        ];
    }

    private function packageIncludesIdeaValidation(EntrepreneurProfile $profile): bool
    {
        return (bool) $this->packageAccessPayload($profile)['includes_idea_validation'];
    }

    private function packageIncludesPlanBudget(EntrepreneurProfile $profile): bool
    {
        return (bool) $this->packageAccessPayload($profile)['includes_plan_budget'];
    }

    private function activeEntrepreneurActivationForProfile(EntrepreneurProfile $profile): ?ServiceActivation
    {
        $activation = ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('related_entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->first();

        if ($activation instanceof ServiceActivation) {
            return $activation;
        }

        if ($profile->client_id === null) {
            return null;
        }

        return ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->where('client_id', $profile->client_id)
            ->latest()
            ->first();
    }

    private function packageLockedResponse(string $message): RedirectResponse
    {
        return to_route('portal.entrepreneur.plan.show')
            ->with('status', 'entrepreneur-package-locked')
            ->with('entrepreneur_plan_error', $message);
    }

    private function latestPlan(EntrepreneurProfile $profile): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with('phases.sections', 'assessments.ratingFramework.criteria')
            ->latest('updated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(EntrepreneurProfile $profile): array
    {
        $stage = $profile->currentStage();

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'email' => $profile->email,
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'concept_summary' => $profile->concept_summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readinessPayload(EntrepreneurProfile $profile): array
    {
        $assessment = ReadinessAssessment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('assessed_at')
            ->latest()
            ->first();

        return [
            'completed' => $assessment instanceof ReadinessAssessment,
            'score' => $assessment?->score,
            'outcome' => $assessment?->outcome,
            'assessed_at' => $assessment?->assessed_at?->toIso8601String(),
            'personal_barriers' => $assessment?->personal_barriers ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ideaValidationPayload(EntrepreneurProfile $profile): ?array
    {
        $validation = $this->latestIdeaValidation($profile);

        if (! $validation instanceof IdeaValidation) {
            return null;
        }

        return [
            'id' => $validation->id,
            'problem' => $validation->problem,
            'target_customer' => $validation->target_customer,
            'solution' => $validation->solution,
            'value_proposition' => $validation->value_proposition,
            'demand_signal' => $validation->demand_signal,
            'revenue_model' => $validation->revenue_model,
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'viability_alerts' => $validation->viability_alerts ?? [],
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'plan_builder_unlocked' => $validation->advisor_gate_passed_at !== null,
        ];
    }

    private function latestIdeaValidation(EntrepreneurProfile $profile): ?IdeaValidation
    {
        return IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('evaluated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections', 'assessments.ratingFramework.criteria', 'budgetRunway');
        $phasesByKey = $plan->phases->keyBy('key');
        $requirements = $this->requirementsPayload($plan);
        $completion = $this->requirementsCompletion($plan, $requirements);
        $latestAssessment = $plan->assessments->sortByDesc('round')->first();

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'completed_at' => $plan->completed_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'requirements_complete' => $completion['complete'],
            'missing_requirements' => $completion['missing'],
            'budget' => $this->budgetPayload($plan, $plan->budgetRunway),
            'latest_assessment' => $latestAssessment ? [
                'id' => $latestAssessment->id,
                'round' => $latestAssessment->round,
                'status' => $latestAssessment->finalised_at === null ? 'in_review' : 'completed',
                'overall_grade' => $latestAssessment->overall_grade,
                'finalised_at' => $latestAssessment->finalised_at?->toIso8601String(),
                'url' => route('portal.entrepreneur.assessments.show', $latestAssessment, absolute: false),
            ] : null,
            'phases' => collect(PlanRequirements::definitions())
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
                            ->map(fn (PlanSection $section): array => [
                                'id' => $section->id,
                                'title' => $section->title,
                                'body' => $section->body,
                                'source_type' => $section->source_type,
                                'completeness_status' => $section->completeness_status,
                                'attached_document_ids' => $section->attached_document_ids ?? [],
                                'predictive_score' => $section->predictive_score,
                                'guidance' => data_get($section->metadata, 'ai_guidance'),
                                'requirement_key' => data_get($section->metadata, 'requirement_key'),
                                'guidance_url' => route('portal.entrepreneur.plan.sections.guidance', $section, absolute: false),
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
    private function planTemplatePayload(): array
    {
        return collect(PlanRequirements::definitions())
            ->map(fn (array $definition, string $phaseKey): array => [
                'key' => $phaseKey,
                'title' => $definition['title'],
                'requirements' => collect($definition['requirements'])
                    ->map(fn (array $requirement): array => [
                        ...$requirement,
                        'phase_key' => $phaseKey,
                        'phase_title' => $definition['title'],
                        'complete' => false,
                        'section_id' => null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function requirementsPayload(BusinessPlan $plan): array
    {
        $plan->loadMissing('budgetRunway');
        $sections = $plan->sections;
        $budget = $plan->budgetRunway;

        return collect(PlanRequirements::definitions())
            ->mapWithKeys(function (array $definition, string $phaseKey) use ($sections, $budget): array {
                return [
                    $phaseKey => collect($definition['requirements'])
                        ->map(function (array $requirement) use ($phaseKey, $definition, $sections, $budget): array {
                            $section = $sections->first(fn (PlanSection $candidate): bool => (
                                (string) data_get($candidate->metadata, 'requirement_key') === $requirement['key']
                                || $candidate->key === 'founder-'.$phaseKey.'-'.$requirement['key']
                            ));
                            $isBudget = ($requirement['type'] ?? null) === 'budget';

                            return [
                                ...$requirement,
                                'phase_key' => $phaseKey,
                                'phase_title' => $definition['title'],
                                'complete' => $isBudget
                                    ? $budget instanceof EntrepreneurBudget && $budget->status === EntrepreneurBudget::STATUS_COMPLETE
                                    : $section instanceof PlanSection && $section->completeness_status === PlanSection::STATUS_COMPLETE,
                                'section_id' => $section?->id,
                                'section_title' => $section?->title,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetPayload(BusinessPlan $plan, ?EntrepreneurBudget $budget): array
    {
        $packAvailable = $this->budgetUnlocked($plan);

        return [
            'id' => $budget?->id,
            'expected_runway_months' => $budget?->expected_runway_months,
            'forecast_years' => $budget?->forecast_years ?? 3,
            'status' => $budget?->status ?? EntrepreneurBudget::STATUS_NOT_STARTED,
            'assumptions' => $budget?->assumptions ?? [],
            'launch_costs' => $budget?->launch_costs ?? [],
            'monthly_fixed_costs' => $budget?->monthly_fixed_costs ?? [],
            'future_costs' => $budget?->future_costs ?? [],
            'revenue_forecast' => $budget?->revenue_forecast ?? [],
            'funding_sources' => $budget?->funding_sources ?? [],
            'funding_scenarios' => $budget?->funding_scenarios ?? [],
            'computed' => $budget?->computed ?? [
                'forecast_years' => 3,
                'total_launch_costs' => 0,
                'monthly_fixed_costs' => 0,
                'total_funding' => 0,
                'available_after_launch' => 0,
                'runway_months' => null,
                'runway_open_ended' => false,
                'break_even_month' => null,
                'break_even_year' => null,
                'first_profitable_year' => null,
                'cash_flow_positive_year' => null,
                'break_even_reached' => false,
                'annual_totals' => [],
                'missing_assumptions' => [],
                'explanations' => [],
                'monthly_series' => [],
                'populated_inputs' => [],
            ],
            'flags' => $budget?->flags ?? [],
            'active_flags' => $budget instanceof EntrepreneurBudget ? $this->budgets->activeFlags($budget) : [],
            'advisor_line_nudge_seen_at' => $budget?->advisor_line_nudge_seen_at?->toIso8601String(),
            'pack_available' => $packAvailable,
            'budget_pack_url' => $packAvailable ? route('portal.entrepreneur.plan.budget-pack.show', absolute: false) : null,
            'budget_pack_pdf_url' => $packAvailable ? route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false) : null,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>|null  $requirements
     * @return array{complete:bool,missing:array<int, string>,completed:array<int, string>}
     */
    private function requirementsCompletion(BusinessPlan $plan, ?array $requirements = null): array
    {
        $requirements ??= $this->requirementsPayload($plan);
        $flattened = collect($requirements)->flatMap(fn (array $rows): array => $rows)->values();
        $missing = $flattened
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => $requirement['phase_title'].': '.$requirement['title'])
            ->values()
            ->all();

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'completed' => $flattened
                ->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    private function budgetUnlocked(BusinessPlan $plan): bool
    {
        $plan->loadMissing('sections');

        return $this->requirementComplete($plan, 'foundation', self::BUDGET_UNLOCK_REQUIREMENT_KEY)
            && $this->requirementComplete($plan, 'financial', self::BUDGET_ASSUMPTIONS_REQUIREMENT_KEY);
    }

    private function requirementComplete(BusinessPlan $plan, string $phaseKey, string $requirementKey): bool
    {
        $plan->loadMissing('sections');

        return $plan->sections->contains(fn (PlanSection $section): bool => (
            $section->completeness_status === PlanSection::STATUS_COMPLETE
            && (
                (string) data_get($section->metadata, 'requirement_key') === $requirementKey
                || $section->key === 'founder-'.$phaseKey.'-'.$requirementKey
            )
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templatePreviewPhases(): array
    {
        return collect($this->planTemplatePayload())
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
     * @return array<int, array<string, mixed>>
     */
    private function reportPayloads(EntrepreneurProfile $profile): array
    {
        return Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function (Report $report): array {
                $url = route('portal.reports.show', $report, absolute: false);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'type' => $report->type->value,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'view_url' => $url,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function advisoryRequestPayload(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signal = $this->latestReadinessSignal($profile);
        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::ADVISORY_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        return [
            'available' => $signal instanceof AdvisoryReadinessSignal && ! ($thread instanceof MessageThread) && ! $plan?->client_id,
            'requested' => $thread instanceof MessageThread,
            'request_url' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            'thread_url' => $thread instanceof MessageThread ? route('portal.messages.show', $thread, absolute: false) : null,
            'blockers' => $signal instanceof AdvisoryReadinessSignal ? [] : ['Finalised advisory readiness is not available yet.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gamificationPayload(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::GAMIFICATION_DISABLE_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        return [
            ...$this->gamification->payload($profile, $plan instanceof BusinessPlan ? $plan : null),
            'disable_request_url' => route('portal.entrepreneur.gamification.disable-request', absolute: false),
            'disable_request_requested' => $thread instanceof MessageThread,
            'disable_request_thread_url' => $thread instanceof MessageThread
                ? route('portal.messages.show', $thread, absolute: false)
                : null,
        ];
    }

    private function latestReadinessSignal(EntrepreneurProfile $profile): ?AdvisoryReadinessSignal
    {
        return AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function requirement(string $phaseKey, string $requirementKey): array
    {
        $requirement = collect(PlanRequirements::definitions()[$phaseKey]['requirements'] ?? [])
            ->first(fn (array $definition): bool => $definition['key'] === $requirementKey);
        abort_unless(is_array($requirement), 422);

        return $requirement;
    }

    private function assertSectionBelongsToProfile(PlanSection $section, EntrepreneurProfile $profile): void
    {
        $section->loadMissing('businessPlan');
        $plan = $section->businessPlan;

        abort_unless(
            $plan instanceof BusinessPlan
            && $plan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR
            && (string) $plan->entrepreneur_profile_id === (string) $profile->getKey(),
            404,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function previewHtml(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases): string
    {
        $requirements = collect($phases)->flatMap(fn (array $phase): array => $phase['requirements'] ?? []);
        $total = $requirements->count();
        $completed = $requirements->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))->count();
        $missingHtml = $requirements
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => '<li>'.$this->escape(($requirement['phase_title'] ?? 'Plan').': '.$requirement['title']).'</li>')
            ->implode('');
        $phaseHtml = collect($phases)
            ->map(fn (array $phase): string => $this->previewPhaseHtml($phase))
            ->implode('');
        $generatedAt = now()->format('M j, Y g:i A');

        return $this->layout->document(
            title: 'Business plan preview - '.$profile->name,
            templateKey: 'business-plan-preview',
            documentTag: 'Business plan preview',
            eyebrow: 'Entrepreneur business plan',
            heading: 'Business plan preview',
            subheading: $profile->name,
            meta: [
                'Plan status' => $this->formatLabel($plan?->status ?? 'not started'),
                'Requirements' => "{$completed}/{$total} complete",
                'Stage' => $this->formatLabel($profile->currentStageValue()),
            ],
            contentHtml: ($missingHtml === '' ? '' : '<article class="report-section missing-panel"><h2>Open items before finalising</h2><ul>'.$missingHtml.'</ul></article>').$phaseHtml,
            footer: 'Generated '.$generatedAt.' using Future Shift Advisory business plan preview',
        );
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function previewPhaseHtml(array $phase): string
    {
        $sections = collect($phase['sections'] ?? []);
        $requirementHtml = collect($phase['requirements'] ?? [])
            ->map(fn (array $requirement): string => $this->previewRequirementHtml($requirement, $sections))
            ->implode('');

        return sprintf(
            '<article class="report-section"><h2>%s</h2>%s</article>',
            $this->escape((string) $phase['title']),
            $requirementHtml,
        );
    }

    private function previewRequirementHtml(array $requirement, Collection $sections): string
    {
        $section = $sections->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key']);
        $complete = (bool) ($requirement['complete'] ?? false);
        $content = is_array($section)
            ? $this->previewSectionContentHtml($section)
            : '<p class="body">Pending input: '.$this->escape((string) $requirement['description']).'</p>';

        return sprintf(
            '<article class="requirement"><h3>%s <span class="status %s">%s</span></h3>%s</article>',
            $this->escape((string) $requirement['title']),
            $complete ? 'complete' : 'pending',
            $complete ? 'Complete' : 'Pending',
            $content,
        );
    }

    private function previewSectionContentHtml(array $section): string
    {
        $documentCount = count((array) ($section['attached_document_ids'] ?? []));
        $docs = $documentCount === 1 ? '1 supporting document' : "{$documentCount} supporting documents";

        return sprintf(
            '<div class="body">%s</div><p class="note">Evidence: %s.</p>',
            nl2br($this->escape((string) ($section['body'] ?? ''))),
            $this->escape($docs),
        );
    }

    private function formatLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
