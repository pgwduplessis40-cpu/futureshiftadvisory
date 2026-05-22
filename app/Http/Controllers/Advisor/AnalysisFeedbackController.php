<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\User;
use App\Services\Analysis\FeedbackRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class AnalysisFeedbackController extends Controller
{
    public function store(Request $request, AnalysisFinding $analysisFinding, FeedbackRecorder $recorder): RedirectResponse
    {
        $analysisFinding->loadMissing(['client', 'run']);
        Gate::authorize('view', $analysisFinding->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(AnalysisFeedback::decisions())],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5', 'required_if:decision,'.AnalysisFeedback::DECISION_RATE],
            'corrected_body' => ['nullable', 'string', 'max:5000', 'required_if:decision,'.AnalysisFeedback::DECISION_CORRECT],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,'.AnalysisFeedback::DECISION_ADD_CONTEXT],
        ]);

        $recorder->record(
            finding: $analysisFinding,
            advisor: $user,
            decision: (string) $validated['decision'],
            rating: isset($validated['rating']) ? (int) $validated['rating'] : null,
            correctedBody: $validated['corrected_body'] ?? null,
            note: $validated['note'] ?? null,
        );

        return back()->with('status', 'analysis-feedback-recorded');
    }
}
