<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\AssessmentFeedback;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurAssessmentController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(private readonly AssessmentFeedback $feedbacks) {}

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
        $feedback = trim((string) ($notes['advisor_feedback'] ?? $notes['overall_visible'] ?? ''));
        if ($feedback === '') {
            $feedback = $this->feedbacks->draft($planAssessment);
        }
        $proposedReply = trim((string) ($notes['proposed_reply'] ?? ''));
        if ($proposedReply === '') {
            $proposedReply = $this->feedbacks->proposedReply($profile, $feedback);
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
            'assessment' => $this->assessmentPayload($planAssessment),
            'advisorFeedback' => [
                'feedback' => $feedback,
                'proposed_reply' => $proposedReply,
                'sent_at' => $notes['feedback_sent_at'] ?? null,
                'action_url' => route('advisor.entrepreneurs.assessments.feedback.update', [$profile, $planAssessment], absolute: false),
            ],
            'dashboardUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backLabel' => 'Entrepreneur',
            'reassessUrl' => $plan
                ? route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false)
                : null,
        ]);
    }
}
