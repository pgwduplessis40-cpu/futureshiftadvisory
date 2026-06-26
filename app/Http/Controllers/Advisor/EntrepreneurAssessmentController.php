<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurAssessmentController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

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
            'dashboardUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backUrl' => route('advisor.entrepreneurs.show', $profile, absolute: false),
            'backLabel' => 'Entrepreneur',
            'reassessUrl' => $plan
                ? route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false)
                : null,
        ]);
    }
}
