<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Entrepreneurs\EntrepreneurJourney;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurAdvisoryServicesController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurJourney $journey,
    ) {}

    public function __invoke(Request $request): Response
    {
        $profile = $this->workspace->profileFor($this->workspace->user($request));
        $journey = $this->journey->payload(
            $profile,
            $this->workspace->packageAccess($profile),
            $this->workspace->latestPlan($profile),
        );

        return Inertia::render('portal/entrepreneur/AdvisoryServices', [
            'advisory' => $journey['advisory'],
            'assessmentStatus' => $journey['assessment']['status_label'],
            'requestUrl' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            'workspaceUrl' => route('portal.entrepreneur.plan.show', ['journey' => 'plan-budget'], absolute: false),
        ]);
    }
}
