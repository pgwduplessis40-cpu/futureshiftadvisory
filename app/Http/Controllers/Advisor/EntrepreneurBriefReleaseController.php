<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Entrepreneurs\ExecutiveSummaryRecovery;
use App\Services\Entrepreneurs\FounderBriefRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EntrepreneurBriefReleaseController extends Controller
{
    public function retry(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, FounderBriefRelease $release, ExecutiveSummaryRecovery $recovery): RedirectResponse
    {
        $advisor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $status = $release->evaluate($businessPlan, $entrepreneurProfile);
        if (! $status['summary_recovery']['can_retry']) {
            throw ValidationException::withMessages(['executive_summary' => $status['reasons'][0] ?? $status['summary_recovery']['message']]);
        }
        try {
            $recovery->request(PlanAssessment::query()->findOrFail($status['assessment_id']), $advisor);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['executive_summary' => 'The summary could not be queued. Please retry; your assessment and advisor decision have been preserved.']);
        }

        return back()->with('status', 'entrepreneur-summary-retry-queued');
    }

    public function approve(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, FounderBriefRelease $release): RedirectResponse
    {
        $advisor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        if (is_string($request->input('note'))) {
            $request->merge(['note' => trim($request->input('note'))]);
        }
        $data = $request->validate([
            'note' => ['required', 'string', 'min:15', 'max:2000'],
            'approval_token' => ['required', 'string', 'size:64'],
            'acknowledged' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($businessPlan, $entrepreneurProfile, $advisor, $data, $release): void {
            $plan = BusinessPlan::query()->lockForUpdate()->findOrFail($businessPlan->getKey());
            $release->approve($plan, $entrepreneurProfile, $advisor, $data['approval_token'], $data['note']);
        });

        return back()->with('status', 'entrepreneur-brief-release-approved');
    }

    private function authorise(Request $request, EntrepreneurProfile $profile, BusinessPlan $plan): User
    {
        Gate::authorize('finaliseAssessment', $profile);
        abort_unless((string) $plan->entrepreneur_profile_id === (string) $profile->getKey(), 404);
        $advisor = $request->user();
        abort_unless($advisor instanceof User, 403);

        return $advisor;
    }
}
