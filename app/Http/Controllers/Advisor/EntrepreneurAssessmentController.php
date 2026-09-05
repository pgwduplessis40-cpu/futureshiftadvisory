<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\AssessmentFeedback;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class EntrepreneurAssessmentController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly AssessmentFeedback $feedbacks,
        private readonly BusinessPlanPreviewRenderer $planPreview,
    ) {}

    public function show(EntrepreneurProfile $entrepreneurProfile, PlanAssessment $planAssessment): Response
    {
        Gate::authorize('view', $entrepreneurProfile);

        $planAssessment->loadMissing(
            'businessPlan.entrepreneurProfile.assignedAdvisor',
            'ratingFramework.criteria',
        );

        $profile = $planAssessment->businessPlan?->entrepreneurProfile;
        abort_unless(
            $profile instanceof EntrepreneurProfile
                && (string) $profile->getKey() === (string) $entrepreneurProfile->getKey(),
            404,
        );
        $plan = $planAssessment->businessPlan;
        $notes = $planAssessment->mentor_notes;
        if (! is_array($notes)) {
            $notes = [];
        }
        $priorities = $this->feedbacks->priorities($planAssessment);
        $suggestedFeedback = $this->feedbacks->draft($planAssessment);
        $suggestedReply = $this->feedbacks->proposedReply($profile, $planAssessment);
        $feedback = trim((string) ($notes['advisor_feedback'] ?? $notes['overall_visible'] ?? ''));
        if ($feedback === '' || $this->feedbacks->isLegacyFeedback($feedback)) {
            $feedback = $suggestedFeedback;
        }
        $proposedReply = trim((string) ($notes['proposed_reply'] ?? ''));
        if ($proposedReply === '' || $this->feedbacks->isLegacyReply($proposedReply)) {
            $proposedReply = $suggestedReply;
        }

        $assessmentPayload = $this->assessmentPayload($planAssessment, includeEvidenceAudit: true);
        if ((bool) data_get($assessmentPayload, 'basis.plan_snapshot_available')) {
            $assessmentPayload['basis']['plan_snapshot_url'] = route(
                'advisor.entrepreneurs.assessments.plan-preview',
                [$profile, $planAssessment],
                absolute: false,
            );
        }

        return Inertia::render('portal/entrepreneur/Assessment', [
            'profile' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'email' => $profile->email,
                'assigned_advisor' => $profile->assignedAdvisor ? [
                    'id' => $profile->assignedAdvisor->id,
                    'name' => $profile->assignedAdvisor->name,
                    'email' => $profile->assignedAdvisor->email,
                ] : null,
            ],
            'assessment' => $assessmentPayload,
            'advisorFeedback' => [
                'feedback' => $feedback,
                'proposed_reply' => $proposedReply,
                'priorities' => $priorities,
                'suggested_feedback' => $suggestedFeedback,
                'suggested_reply' => $suggestedReply,
                'sent_at' => $notes['feedback_sent_at'] ?? null,
                'action_url' => route('advisor.entrepreneurs.assessments.feedback.update', [$profile, $planAssessment], absolute: false),
            ],
            'advisorScoringReview' => Gate::allows('finaliseAssessment', $profile)
                && (bool) data_get($assessmentPayload, 'scoring_scope.advisor_review_required', false)
                && empty(data_get($assessmentPayload, 'scoring_scope.advisor_review_confirmed_at'))
                ? [
                    'action_url' => route('advisor.entrepreneurs.assessments.scoring-scope.confirm', [$profile, $planAssessment], absolute: false),
                ]
                : null,
            'dashboardUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backLabel' => 'Entrepreneur',
            'reassessUrl' => $plan
                ? route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false)
                : null,
        ]);
    }

    public function planPreview(EntrepreneurProfile $entrepreneurProfile, PlanAssessment $planAssessment): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $planAssessment->loadMissing('businessPlan.entrepreneurProfile');
        $profile = $planAssessment->businessPlan?->entrepreneurProfile;
        $plan = $planAssessment->businessPlan;
        abort_unless(
            $profile instanceof EntrepreneurProfile
                && $plan instanceof BusinessPlan
                && (string) $profile->getKey() === (string) $entrepreneurProfile->getKey(),
            404,
        );

        $snapshot = $planAssessment->plan_snapshot;
        abort_unless(is_array($snapshot) && is_array($snapshot['phases'] ?? null), 404, 'No submitted plan snapshot is available for this assessment round.');

        $pdf = $this->planPreview->pdfFromSnapshot($profile, $plan, $snapshot, (int) $planAssessment->round);
        $filename = Str::slug($profile->name ?: 'entrepreneur').'-submitted-plan-round-'.$planAssessment->round.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
