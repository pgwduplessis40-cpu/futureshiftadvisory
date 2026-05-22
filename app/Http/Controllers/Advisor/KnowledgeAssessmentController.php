<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Analysis\KnowledgeCalibration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class KnowledgeAssessmentController extends Controller
{
    public function store(Request $request, Client $client, KnowledgeCalibration $calibration): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'financial_literacy' => ['required', 'integer', 'min:1', 'max:5'],
            'strategic_awareness' => ['required', 'integer', 'min:1', 'max:5'],
            'leadership' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $calibration->assess(
            client: $client,
            advisor: $user,
            financialLiteracy: (int) $validated['financial_literacy'],
            strategicAwareness: (int) $validated['strategic_awareness'],
            leadership: (int) $validated['leadership'],
        );

        return back()->with('status', 'knowledge-assessment-recorded');
    }
}
