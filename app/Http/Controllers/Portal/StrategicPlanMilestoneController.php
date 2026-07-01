<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Services\Portal\ClientPortalResolver;
use App\Services\StrategicPlans\StrategicPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class StrategicPlanMilestoneController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly StrategicPlanService $plans,
    ) {}

    public function update(Request $request, StrategicPlanMilestone $milestone): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        abort_unless((string) $milestone->client_id === (string) $client->getKey(), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'completed', 'blocked'])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'evidence_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->plans->updateClientMilestone($milestone, $validated, $user);
        } catch (InvalidArgumentException $exception) {
            return to_route('portal.dashboard')
                ->withErrors(['strategic_plan' => $exception->getMessage()]);
        }

        return to_route('portal.dashboard')->with('status', 'strategic-plan-milestone-updated');
    }
}
