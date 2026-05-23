<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        $profile = EntrepreneurProfile::query()
            ->with(['businessPlans.assessments', 'advisoryReadinessSignals'])
            ->where('user_id', $user->getKey())
            ->first();
        $latestPlan = $profile?->businessPlans
            ->sortByDesc('updated_at')
            ->first();
        $latestSignal = $profile?->advisoryReadinessSignals
            ->sortByDesc('surfaced_at')
            ->first();

        return Inertia::render('portal/entrepreneur/Dashboard', [
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'email' => $profile->email,
                'stage' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->value
                    : (string) $profile->stage,
                'stage_label' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->label()
                    : EntrepreneurStage::from((string) $profile->stage)->label(),
                'concept_summary' => $profile->concept_summary,
                'latest_plan' => $latestPlan instanceof BusinessPlan ? [
                    'id' => $latestPlan->id,
                    'status' => $latestPlan->status,
                    'assessment_count' => $latestPlan->assessments->count(),
                    'latest_grade' => $latestPlan->assessments->sortByDesc('round')->first()?->overall_grade,
                    'living_plan_next_update_at' => $latestPlan->living_plan_next_update_at?->toIso8601String(),
                    'living_plan_divergence_flags' => $latestPlan->living_plan_divergence_flags,
                ] : null,
                'advisory_readiness_signal' => $latestSignal ? [
                    'score' => $latestSignal->score,
                    'surfaced_at' => $latestSignal->surfaced_at?->toIso8601String(),
                ] : null,
            ] : null,
        ]);
    }
}
