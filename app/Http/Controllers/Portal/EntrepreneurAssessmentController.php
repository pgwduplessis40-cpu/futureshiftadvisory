<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurAssessmentController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(private readonly EntrepreneurInviteReconciler $entrepreneurInvites) {}

    public function show(Request $request, PlanAssessment $planAssessment): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);
        $this->entrepreneurInvites->reconcile($user);

        $planAssessment->loadMissing(
            'businessPlan.entrepreneurProfile.assignedAdvisor',
            'ratingFramework.criteria',
        );

        $profile = $planAssessment->businessPlan?->entrepreneurProfile;
        abort_unless(
            $profile instanceof EntrepreneurProfile && (int) $profile->user_id === (int) $user->getKey(),
            403,
        );

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
            'dashboardUrl' => route('portal.entrepreneur.dashboard', absolute: false),
            'backUrl' => route('portal.entrepreneur.dashboard', absolute: false),
        ]);
    }
}
