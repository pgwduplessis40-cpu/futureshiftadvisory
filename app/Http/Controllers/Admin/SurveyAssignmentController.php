<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\Surveys\SurveyActivationService;
use App\Services\Surveys\SurveyLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SurveyAssignmentController extends Controller
{
    public function storeForClient(
        Request $request,
        Client $client,
        SurveyActivationService $activation,
        SurveyLibrary $library,
    ): RedirectResponse {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $survey = $this->resolveSurvey($request, $library, $user);
        $assignment = $activation->activateForClient($client, $survey, $user, $this->dueAt($request));

        return to_route('advisor.clients.surveys', $client)->with('status', 'survey-activated')->with('survey_assignment_id', $assignment->getKey());
    }

    public function storeForEntrepreneur(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        SurveyActivationService $activation,
        SurveyLibrary $library,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $survey = $this->resolveSurvey($request, $library, $user);
        $assignment = $activation->activateForEntrepreneur($entrepreneurProfile, $survey, $user, $this->dueAt($request));

        return to_route('advisor.entrepreneurs.surveys', $entrepreneurProfile)->with('status', 'survey-activated')->with('survey_assignment_id', $assignment->getKey());
    }

    public function cancel(Request $request, SurveyAssignment $surveyAssignment, SurveyActivationService $activation): RedirectResponse
    {
        Gate::authorize('cancel', $surveyAssignment);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $activation->cancel($surveyAssignment, $user);

        return back()->with('status', 'survey-cancelled');
    }

    private function resolveSurvey(Request $request, SurveyLibrary $library, User $user): Survey
    {
        $validated = $request->validate([
            'survey_id' => ['nullable', 'uuid', Rule::exists('surveys', 'id')],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (isset($validated['survey_id'])) {
            return Survey::query()->whereKey($validated['survey_id'])->firstOrFail();
        }

        $survey = Survey::query()
            ->published()
            ->latest('published_at')
            ->first();

        return $survey instanceof Survey ? $survey : $library->ensureDefault($user);
    }

    private function dueAt(Request $request): ?Carbon
    {
        $value = $request->input('due_at');

        return is_string($value) && trim($value) !== '' ? Carbon::parse($value) : null;
    }
}
