<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class EntrepreneurAssessmentController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly BusinessPlanPreviewRenderer $planPreview,
    ) {}

    public function show(Request $request, PlanAssessment $planAssessment): Response
    {
        $profile = $this->profileForAssessment($request, $planAssessment);
        $planAssessment->loadMissing('ratingFramework.criteria');

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
            'assessment' => $this->assessmentPayload($planAssessment, includeEvidenceAudit: true),
            'dashboardUrl' => route('portal.entrepreneur.dashboard', absolute: false),
            'backUrl' => route('portal.entrepreneur.dashboard', absolute: false),
        ]);
    }

    public function planPreview(Request $request, PlanAssessment $planAssessment): SymfonyResponse
    {
        $profile = $this->profileForAssessment($request, $planAssessment);
        $plan = $planAssessment->businessPlan;
        abort_unless($plan instanceof BusinessPlan, 404);

        $snapshot = $planAssessment->plan_snapshot;
        abort_unless(
            is_array($snapshot) && is_array($snapshot['phases'] ?? null),
            404,
            'No submitted plan snapshot is available for this assessment round.',
        );

        $pdf = $this->planPreview->pdfFromSnapshot($profile, $plan, $snapshot, (int) $planAssessment->round);
        $filename = Str::slug($profile->name ?: 'entrepreneur').'-submitted-plan-round-'.$planAssessment->round.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function profileForAssessment(Request $request, PlanAssessment $planAssessment): EntrepreneurProfile
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);
        $this->entrepreneurInvites->reconcile($user);

        $planAssessment->loadMissing('businessPlan.entrepreneurProfile.assignedAdvisor');

        $profile = $planAssessment->businessPlan?->entrepreneurProfile;
        abort_unless(
            $profile instanceof EntrepreneurProfile && (int) $profile->user_id === (int) $user->getKey(),
            403,
        );

        return $profile;
    }
}
