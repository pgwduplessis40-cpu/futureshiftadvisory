<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\SurveyAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\Surveys\SurveyResponseRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurSurveyController extends Controller
{
    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        return Inertia::render('portal/entrepreneur/surveys/Index', [
            'subject' => [
                'type' => 'entrepreneur',
                'name' => $profile->name,
            ],
            'assignments' => SurveyAssignment::query()
                ->with(['survey', 'response'])
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->latest('activated_at')
                ->get()
                ->map(fn (SurveyAssignment $assignment): array => $this->assignmentPayload($assignment))
                ->values(),
            'dashboardUrl' => route('portal.entrepreneur.dashboard', absolute: false),
        ]);
    }

    public function show(Request $request, SurveyAssignment $surveyAssignment): Response
    {
        $this->profile($request);
        Gate::authorize('view', $surveyAssignment);

        return Inertia::render('portal/entrepreneur/surveys/Show', [
            'assignment' => $this->assignmentPayload($surveyAssignment->load('survey.questions', 'response')),
            'storeUrl' => route('portal.entrepreneur.surveys.submit', $surveyAssignment, absolute: false),
            'indexUrl' => route('portal.entrepreneur.surveys.index', absolute: false),
        ]);
    }

    public function submit(Request $request, SurveyAssignment $surveyAssignment, SurveyResponseRecorder $recorder): RedirectResponse
    {
        $this->profile($request);
        Gate::authorize('respond', $surveyAssignment);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recorder->record($surveyAssignment, $user, $request->all());

        return to_route('portal.entrepreneur.surveys.index')->with('status', 'survey-submitted');
    }

    private function profile(Request $request): EntrepreneurProfile
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(SurveyAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'survey_title' => $assignment->survey?->title,
            'survey_description' => $assignment->survey?->description,
            'status' => $assignment->status?->value,
            'is_open' => in_array($assignment->status?->value, SurveyAssignmentStatus::activeValues(), true),
            'activated_at' => $assignment->activated_at?->toIso8601String(),
            'due_at' => $assignment->due_at?->toIso8601String(),
            'completed_at' => $assignment->completed_at?->toIso8601String(),
            'deliverables' => $assignment->deliverable_snapshot ?? [],
            'url' => route('portal.entrepreneur.surveys.show', $assignment, absolute: false),
            'response' => $assignment->response ? [
                'overall_score' => $assignment->response->overall_score,
                'nps_score' => $assignment->response->nps_score,
                'submitted_at' => $assignment->response->submitted_at?->toIso8601String(),
            ] : null,
            'questions' => $assignment->survey?->questions
                ->map(fn ($question): array => [
                    'id' => $question->id,
                    'order' => $question->order,
                    'type' => $question->type?->value,
                    'key' => $question->key,
                    'prompt' => $question->prompt,
                    'help_text' => $question->help_text,
                    'required' => $question->required,
                    'options' => $question->options,
                ])
                ->values()
                ->all() ?? [],
        ];
    }
}
