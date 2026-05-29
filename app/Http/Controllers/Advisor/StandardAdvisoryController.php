<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class StandardAdvisoryController extends Controller
{
    public function runAnalysis(Request $request, Client $client, StandardAdvisoryWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workflow->runAnalysis($client, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'standard-advisory-analysis-run');
    }

    public function generatePack(Request $request, Client $client, StandardAdvisoryWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workflow->generateAdvisoryPack($client, $user);

        return to_route('advisor.clients.show', $client)->with('status', 'standard-advisory-pack-generated');
    }
}
