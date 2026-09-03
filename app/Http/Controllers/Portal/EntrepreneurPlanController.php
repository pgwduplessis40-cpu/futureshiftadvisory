<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PlanSection;
use App\Models\ServiceRatePackage;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanAiContext;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Services\Entrepreneurs\Readiness;
use App\Services\Entrepreneurs\Revision;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class EntrepreneurPlanController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanRequirements $requirements,
        private readonly Readiness $readiness,
        private readonly IdeaValidationService $ideas,
        private readonly PlanBuilder $plans,
        private readonly SharedPlanBuilder $sharedPlans,
        private readonly Guidance $guidance,
        private readonly PlanDocuments $documents,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
    ) {}

    public function readiness(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);

        $rules = collect(array_keys(EntrepreneurPlanWorkspacePayload::READINESS_FIELDS))
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
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesIdeaValidation($profile)) {
            return $this->workspace->packageLockedResponse('Idea validation is not included in your selected package.');
        }

        $validated = $request->validate([
            'problem' => ['required', 'string', 'min:5', 'max:10000'],
            'target_customer' => ['required', 'string', 'min:3', 'max:10000'],
            'solution' => ['required', 'string', 'min:10', 'max:10000'],
            'value_proposition' => ['required', 'string', 'min:10', 'max:10000'],
            'demand_signal' => ['required', 'string', 'min:5', 'max:10000'],
            'revenue_model' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        $this->ideas->evaluate($profile, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-idea-submitted');
    }

    public function recallIdeaValidation(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesIdeaValidation($profile)) {
            return $this->workspace->packageLockedResponse('Idea validation is not included in your selected package.');
        }

        $validation = $this->latestIdeaValidation($profile);
        abort_unless($validation instanceof IdeaValidation, 404);

        $this->ideas->recallForRevision($validation, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-idea-recalled');
    }

    public function restoreIdeaValidation(Request $request, IdeaValidation $ideaValidation): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesIdeaValidation($profile)) {
            return $this->workspace->packageLockedResponse('Idea validation is not included in your selected package.');
        }

        abort_unless(
            (string) $ideaValidation->entrepreneur_profile_id === (string) $profile->getKey(),
            404,
        );

        $this->ideas->restoreRevision($profile, $ideaValidation, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-idea-restored');
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);

        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Business plan and budget are not included in your selected package.');
        }

        if (! $this->workspace->includesIdeaValidation($profile)) {
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

    public function updateCompanyName(Request $request): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Business plan and budget are not included in your selected package.');
        }

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:160'],
        ]);

        $profile->forceFill([
            'company_name' => filled($validated['company_name'] ?? null)
                ? trim((string) $validated['company_name'])
                : null,
        ])->save();

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-company-name-saved');
    }

    public function section(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Business plan and budget are not included in your selected package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);
        $this->workspace->assertPlanAcceptsFounderChanges($plan);

        $autosave = $request->boolean('_autosave');
        $validated = $request->validate([
            '_autosave' => ['sometimes', 'boolean'],
            'phase_key' => ['required', 'string', Rule::in(array_keys(PlanRequirements::definitions()))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => [$autosave ? 'nullable' : 'required', 'string', $autosave ? 'min:0' : 'min:80', 'max:'.PlanAiContext::PLAN_SECTION_BODY_MAX_LENGTH],
            'attached_document_ids' => ['array'],
            'attached_document_ids.*' => ['string', 'uuid'],
        ]);

        $phaseKey = (string) $validated['phase_key'];
        $requirementKey = (string) $validated['requirement_key'];
        $this->workspace->assertRequirementCanBeManuallyWritten($requirementKey);
        $requirement = $this->requirements->requirement($phaseKey, $requirementKey);
        $sectionKey = 'founder-'.$phaseKey.'-'.$requirementKey;
        $body = $this->requirements->integrateDatedUpdate((string) ($validated['body'] ?? ''));
        $existingSection = PlanSection::query()
            ->where('business_plan_id', $plan->getKey())
            ->where('key', $sectionKey)
            ->first();
        $completenessStatus = null;

        if ($autosave) {
            $completenessStatus = trim($body) === ''
                ? PlanSection::STATUS_DRAFT
                : ($existingSection instanceof PlanSection ? $existingSection->completeness_status : PlanSection::STATUS_DRAFT);
        }

        $section = $this->plans->upsertSection(
            plan: $plan,
            phaseKey: $phaseKey,
            key: $sectionKey,
            title: (string) ($validated['title'] ?: $requirement['title']),
            body: $body,
            actor: $user,
            metadata: [
                'source' => 'entrepreneur_plan_workspace',
                'requirement_key' => $requirementKey,
                'requirement_title' => $requirement['title'],
                'completed_by_user_id' => $user->getKey(),
                'autosaved_at' => $autosave ? now()->toIso8601String() : null,
            ],
            completenessStatus: $completenessStatus,
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
                    claim: Str::limit($body, 500, ''),
                );
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $autosave ? 'entrepreneur-plan-section-autosaved' : 'entrepreneur-plan-section-saved',
                'section' => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'body' => $section->body,
                    'completeness_status' => $section->completeness_status,
                    'updated_at' => $section->updated_at?->toIso8601String(),
                ],
            ]);
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-section-saved');
    }

    public function assistRequirement(Request $request): JsonResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        abort_unless($this->workspace->includesPlanBudget($profile), 403);
        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);
        $this->workspace->assertPlanAcceptsFounderChanges($plan);

        $validated = $request->validate([
            'phase_key' => ['required', 'string', Rule::in(array_keys(PlanRequirements::definitions()))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'body' => ['nullable', 'string', 'max:'.PlanAiContext::PLAN_SECTION_BODY_MAX_LENGTH],
        ]);
        $phaseKey = (string) $validated['phase_key'];
        $requirement = [
            ...$this->requirements->requirement($phaseKey, (string) $validated['requirement_key']),
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

    public function generateExecutiveSummary(Request $request): RedirectResponse|JsonResponse
    {
        throw ValidationException::withMessages(['executive_summary' => 'The executive summary is generated automatically after the current Business Plan & Budget assessment is finalised and passes.']);
    }

    public function guidance(Request $request, PlanSection $planSection): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        abort_unless($this->workspace->includesPlanBudget($profile), 403);
        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);
        $this->workspace->assertPlanAcceptsFounderChanges($plan);
        $this->assertSectionBelongsToProfile($planSection, $profile);

        $this->guidance->guide($planSection, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-guidance-generated');
    }

    public function submit(Request $request, Revision $revisions): RedirectResponse
    {
        $user = $this->workspace->user($request);
        $profile = $this->workspace->profileFor($user);
        if (! $this->workspace->includesPlanBudget($profile)) {
            return $this->workspace->packageLockedResponse('Business plan assessment is not included in your selected package.');
        }

        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $completion = $this->requirements->completion($plan);
        if (! $completion['complete']) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-plan-requirements-missing')
                ->with('entrepreneur_plan_missing_requirements', $completion['missing']);
        }

        if ($plan->status === BusinessPlan::STATUS_REVISING) {
            $plan->forceFill([
                'founding_advisory_payload' => $this->sharedPlans->foundingPayload($plan),
            ])->save();

            try {
                $revisions->submit($plan->refresh()->load('sections'), $user);
            } catch (Throwable $exception) {
                report($exception);

                return to_route('portal.entrepreneur.plan.show')->withErrors([
                    'plan' => 'Your updated plan could not be sent to your advisor yet. Your saved changes are still safe; please try again shortly.',
                ]);
            }
            $profile->forceFill(['stage' => EntrepreneurStage::SUBMITTED])->save();

            return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-resubmitted-for-advisor-review');
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

    private function latestIdeaValidation(EntrepreneurProfile $profile): ?IdeaValidation
    {
        return IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->first();
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
}
