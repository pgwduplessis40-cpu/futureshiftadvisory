<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidation;
use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurPlanBudgetAccessController extends Controller
{
    public function __invoke(
        Request $request,
        EntrepreneurPlanWorkspace $workspace,
        EntrepreneurServiceOffer $offers,
    ): Response {
        $user = $workspace->user($request);
        $profile = $workspace->profileFor($user);
        $access = $workspace->packageAccess($profile);
        $ideaValidationApproved = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->exists();

        return Inertia::render('portal/entrepreneur/PlanBudgetAccess', [
            'hasPlanBudgetAccess' => $access['includes_plan_budget'],
            'ideaValidationApproved' => $ideaValidationApproved,
            'offer' => $offers->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET),
            'workspaceUrl' => route('portal.entrepreneur.plan.show', absolute: false),
        ]);
    }
}
