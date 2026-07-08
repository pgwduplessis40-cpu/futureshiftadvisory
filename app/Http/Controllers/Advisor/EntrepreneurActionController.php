<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Jobs\RefreshIdeaValidationAiReview;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisoryConversion;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\EntrepreneurStreak;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Reports\ReportComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EntrepreneurActionController extends Controller
{
    public function gateIdea(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validate([
            'advisor_gate_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $ideas->passAdvisorGate($ideaValidation, $advisor, (string) $validated['advisor_gate_note']);

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-gate-passed');
    }

    public function refreshIdea(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        $ideas->markRefreshQueued($ideaValidation, $advisor);
        RefreshIdeaValidationAiReview::dispatch((string) $ideaValidation->getKey(), (int) $advisor->getKey());

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-refresh-queued');
    }

    public function requestIdeaChanges(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validate([
            'change_request_note' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $ideas->requestChanges($ideaValidation, $advisor, (string) $validated['change_request_note']);

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-changes-requested');
    }

    public function assess(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        BusinessPlan $businessPlan,
        Assessment $assessments,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        $assessment = $assessments->firstPass($businessPlan->refresh()->load('sections'), $advisor);
        $entrepreneurProfile->forceFill(['stage' => EntrepreneurStage::ASSESSMENT])->save();

        return to_route('advisor.entrepreneurs.assessments.show', [$entrepreneurProfile, $assessment])
            ->with('status', 'entrepreneur-plan-assessed');
    }

    public function finalise(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        PlanAssessment $planAssessment,
        Assessment $assessments,
        ReportComposer $reports,
        AdvisoryReadiness $readiness,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertAssessmentBelongsToProfile($planAssessment, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        $assessment = $assessments->finalise($planAssessment, $advisor);
        $report = $reports->composeEntrepreneurAssessment($assessment->refresh(), $advisor);
        $plan = $assessment->businessPlan;
        abort_unless($plan instanceof BusinessPlan, 404);
        $signal = $readiness->evaluate($plan->refresh()->load('assessments.ratingFramework.criteria'), $advisor);

        if (! $signal instanceof AdvisoryReadinessSignal) {
            $entrepreneurProfile->forceFill(['stage' => EntrepreneurStage::REVISING])->save();
        }

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', 'entrepreneur-assessment-finalised')
            ->with('entrepreneur_assessment_report_id', $report->getKey());
    }

    public function convert(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        AdvisoryConversion $conversion,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $plan = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $entrepreneurProfile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->latest('updated_at')
            ->latest()
            ->first();
        $signal = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $entrepreneurProfile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-not-advisory-ready');
        }

        $client = $conversion->convert($entrepreneurProfile->refresh()->load('user', 'advisoryReadinessSignals'), $advisor, $plan);

        return to_route('advisor.clients.show', $client)->with('status', 'entrepreneur-converted');
    }

    public function setGamification(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        EntrepreneurMilestones $milestones,
        EntrepreneurStreak $streak,
        AuditWriter $audit,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $validated['enabled'];

        DB::transaction(function () use ($entrepreneurProfile, $enabled, $advisor, $milestones, $streak, $audit): void {
            $before = (bool) $entrepreneurProfile->gamification_on;

            $entrepreneurProfile->forceFill([
                'gamification_on' => $enabled,
            ])->save();

            if ($enabled) {
                $milestones->reconcile($entrepreneurProfile->refresh());
                $streak->recompute($entrepreneurProfile->refresh());
            } else {
                $entrepreneurProfile->forceFill([
                    'current_streak' => 0,
                    'last_active_at' => null,
                ])->save();
            }

            $audit->record($enabled ? 'gamification.enabled' : 'gamification.disabled', subject: $entrepreneurProfile, actor: $advisor, before: [
                'gamification_on' => $before,
            ], after: [
                'gamification_on' => $enabled,
                'entrepreneur_profile_id' => $entrepreneurProfile->getKey(),
            ]);
        });

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', $enabled ? 'entrepreneur-gamification-enabled' : 'entrepreneur-gamification-disabled');
    }

    private function advisor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function assertIdeaBelongsToProfile(IdeaValidation $ideaValidation, EntrepreneurProfile $profile): void
    {
        abort_unless((string) $ideaValidation->entrepreneur_profile_id === (string) $profile->getKey(), 404);
    }

    private function assertPlanBelongsToProfile(BusinessPlan $businessPlan, EntrepreneurProfile $profile): void
    {
        abort_unless(
            $businessPlan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR
            && (string) $businessPlan->entrepreneur_profile_id === (string) $profile->getKey(),
            404,
        );
    }

    private function assertAssessmentBelongsToProfile(PlanAssessment $assessment, EntrepreneurProfile $profile): void
    {
        $assessment->loadMissing('businessPlan');
        $plan = $assessment->businessPlan;

        abort_unless($plan instanceof BusinessPlan, 404);
        $this->assertPlanBelongsToProfile($plan, $profile);
    }
}
