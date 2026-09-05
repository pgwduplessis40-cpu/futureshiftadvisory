<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\Entrepreneurs\AssessmentFeedbackRequest;
use App\Http\Requests\Advisor\Entrepreneurs\PassIdeaGateRequest;
use App\Http\Requests\Advisor\Entrepreneurs\RequestIdeaChangesRequest;
use App\Http\Requests\Advisor\Entrepreneurs\SetGamificationRequest;
use App\Jobs\RefreshIdeaValidationAiReview;
use App\Jobs\RunEntrepreneurPlanAssessment;
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
use App\Services\Entrepreneurs\AssessmentFeedback;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\EntrepreneurStreak;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\Revision;
use App\Services\Messaging\MessageThreadService;
use App\Services\Reports\ReportComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class EntrepreneurActionController extends Controller
{
    public function gateIdea(
        PassIdeaGateRequest $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validated();

        $ideas->passAdvisorGate($ideaValidation, $advisor, (string) $validated['advisor_gate_note']);

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-gate-passed');
    }

    public function refreshIdea(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        Gate::authorize('assess', $entrepreneurProfile);
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        $ideas->markRefreshQueued($ideaValidation, $advisor);
        RefreshIdeaValidationAiReview::dispatch((string) $ideaValidation->getKey(), (int) $advisor->getKey());

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-refresh-queued');
    }

    public function requestIdeaChanges(
        RequestIdeaChangesRequest $request,
        EntrepreneurProfile $entrepreneurProfile,
        IdeaValidation $ideaValidation,
        IdeaValidationService $ideas,
    ): RedirectResponse {
        $this->assertIdeaBelongsToProfile($ideaValidation, $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validated();

        $ideas->requestChanges($ideaValidation, $advisor, (string) $validated['change_request_note']);

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-idea-changes-requested');
    }

    public function assess(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        BusinessPlan $businessPlan,
        Assessment $assessments,
    ): RedirectResponse {
        Gate::authorize('assess', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        try {
            $queued = $assessments->queueFirstPass($businessPlan, $advisor);
            if ($queued) {
                RunEntrepreneurPlanAssessment::dispatch((string) $businessPlan->getKey(), (int) $advisor->getKey());
            }
        } catch (Throwable $exception) {
            $assessments->markQueuedFirstPassFailed($businessPlan, $advisor, $exception);
            report($exception);

            return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
                ->withErrors([
                    'assessment' => 'The assessment could not be queued. Please retry after the issue has been resolved.',
                ]);
        }

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', $queued ? 'entrepreneur-plan-assessment-queued' : 'entrepreneur-plan-assessment-running');
    }

    public function generateExecutiveSummary(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        BusinessPlan $businessPlan,
    ): RedirectResponse {
        Gate::authorize('assess', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->withErrors([
                'executive_summary' => 'The executive summary is generated automatically after the current Business Plan & Budget assessment is finalised and passes.',
            ]);
    }

    public function finalise(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        PlanAssessment $planAssessment,
        Assessment $assessments,
        ReportComposer $reports,
        AdvisoryReadiness $readiness,
    ): RedirectResponse {
        Gate::authorize('finaliseAssessment', $entrepreneurProfile);
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

    public function confirmAssessmentScoringScope(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        PlanAssessment $planAssessment,
        Assessment $assessments,
    ): RedirectResponse {
        Gate::authorize('finaliseAssessment', $entrepreneurProfile);
        $this->assertAssessmentBelongsToProfile($planAssessment, $entrepreneurProfile);
        $advisor = $this->advisor($request);

        $assessment = $assessments->confirmScoringScope($planAssessment, $advisor);

        return to_route('advisor.entrepreneurs.assessments.show', [$entrepreneurProfile, $assessment])
            ->with('status', 'entrepreneur-assessment-scoring-scope-confirmed');
    }

    public function updateAssessmentFeedback(
        AssessmentFeedbackRequest $request,
        EntrepreneurProfile $entrepreneurProfile,
        PlanAssessment $planAssessment,
        Assessment $assessments,
        AssessmentFeedback $feedbacks,
        Revision $revisions,
        MessageThreadService $messages,
    ): RedirectResponse {
        $this->assertAssessmentBelongsToProfile($planAssessment, $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $validated = $request->validated();
        $sendToFounder = (bool) $validated['send_to_founder'];

        $assessment = $assessments->saveAdvisorFeedback(
            assessment: $planAssessment,
            feedback: (string) $validated['feedback'],
            proposedReply: (string) $validated['proposed_reply'],
            sentToFounder: $sendToFounder,
            advisor: $advisor,
            feedbackSnapshot: $feedbacks->snapshot($entrepreneurProfile, $planAssessment),
        );

        if ($sendToFounder) {
            $assessment->loadMissing('businessPlan');
            $plan = $assessment->businessPlan;
            if ($plan instanceof BusinessPlan && in_array($plan->status, [
                BusinessPlan::STATUS_SUBMITTED,
                BusinessPlan::STATUS_ASSESSING,
            ], true)) {
                $revisions->open($plan, $advisor);
            }
            $entrepreneurProfile->forceFill(['stage' => EntrepreneurStage::REVISING])->save();

            $messages->startEntrepreneurThread(
                profile: $entrepreneurProfile,
                sender: $advisor,
                subject: 'Business plan assessment feedback',
                body: (string) $validated['proposed_reply'],
            );
        }

        return to_route('advisor.entrepreneurs.assessments.show', [$entrepreneurProfile, $assessment])
            ->with('status', $sendToFounder
                ? 'entrepreneur-assessment-feedback-sent'
                : 'entrepreneur-assessment-feedback-saved');
    }

    public function convert(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        AdvisoryConversion $conversion,
        AdvisoryReadiness $readiness,
    ): RedirectResponse {
        Gate::authorize('convert', $entrepreneurProfile);
        $advisor = $this->advisor($request);
        $plan = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $entrepreneurProfile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->latest('updated_at')
            ->latest()
            ->first();
        $signal = $readiness->currentSignalForPlan($plan);

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)->with('status', 'entrepreneur-not-advisory-ready');
        }

        $client = $conversion->convert($entrepreneurProfile->refresh()->load('user', 'advisoryReadinessSignals'), $advisor, $plan);

        return to_route('advisor.clients.show', $client)->with('status', 'entrepreneur-converted');
    }

    public function setGamification(
        SetGamificationRequest $request,
        EntrepreneurProfile $entrepreneurProfile,
        EntrepreneurMilestones $milestones,
        EntrepreneurStreak $streak,
        AuditWriter $audit,
    ): RedirectResponse {
        $advisor = $this->advisor($request);
        $validated = $request->validated();
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
