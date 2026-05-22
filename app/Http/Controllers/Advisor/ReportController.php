<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ReportController extends Controller
{
    public function store(Request $request, Client $client, ReportComposer $reports): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in([ReportType::Client->value, ReportType::Advisor->value, ReportType::Stakeholder->value, ReportType::Trajectory->value])],
        ]);

        $reports->compose($client, ReportType::from((string) $validated['type']), $user);

        return to_route('advisor.clients.show', $client)->with('status', 'report-generated');
    }

    public function review(Request $request, Report $report, ReportComposer $reports): RedirectResponse
    {
        $report->loadMissing('client');
        Gate::authorize('view', $report->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reports->markReviewed($report, $user);

        return to_route('advisor.clients.show', $report->client)->with('status', 'report-reviewed');
    }
}
