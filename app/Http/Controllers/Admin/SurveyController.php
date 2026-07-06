<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SurveyQuestionType;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Surveys\SurveyLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SurveyController extends Controller
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function index(Request $request, SurveyLibrary $library): Response
    {
        Gate::authorize('viewAny', Survey::class);

        if (! Survey::query()->exists()) {
            $user = $request->user();
            $library->ensureDefault($user instanceof User ? $user : null);
        }

        return Inertia::render('admin/surveys/Index', [
            'surveys' => Survey::query()
                ->withCount(['questions', 'assignments', 'responses'])
                ->latest()
                ->get()
                ->map(fn (Survey $survey): array => [
                    'id' => $survey->id,
                    'key' => $survey->key,
                    'version' => $survey->version,
                    'title' => $survey->title,
                    'description' => $survey->description,
                    'status' => $survey->status?->value,
                    'published_at' => $survey->published_at?->toIso8601String(),
                    'questions_count' => $survey->questions_count,
                    'assignments_count' => $survey->assignments_count,
                    'responses_count' => $survey->responses_count,
                    'show_url' => route('admin.surveys.show', $survey, absolute: false),
                    'edit_url' => route('admin.surveys.edit', $survey, absolute: false),
                    'publish_url' => route('admin.surveys.publish', $survey, absolute: false),
                    'archive_url' => route('admin.surveys.archive', $survey, absolute: false),
                    'results_url' => route('admin.surveys.results', $survey, absolute: false),
                ])
                ->values(),
            'storeUrl' => route('admin.surveys.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Survey::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $survey = Survey::query()->create([
            ...$validated,
            'status' => SurveyStatus::Draft->value,
            'settings' => [
                'allow_free_text' => false,
                'deliverable_anchor_types' => ['report', 'document', 'plan_assessment'],
            ],
            'created_by_user_id' => $user->getKey(),
        ]);

        $this->audit->record('survey.created', subject: $survey, actor: $user, after: [
            'survey_id' => $survey->getKey(),
            'key' => $survey->key,
            'version' => $survey->version,
        ]);

        return to_route('admin.surveys.edit', $survey)->with('status', 'survey-created');
    }

    public function show(Survey $survey): Response
    {
        Gate::authorize('viewAny', Survey::class);

        return Inertia::render('admin/surveys/Show', [
            'survey' => $this->surveyPayload($survey->load('questions')),
            'indexUrl' => route('admin.surveys.index', absolute: false),
            'editUrl' => route('admin.surveys.edit', $survey, absolute: false),
            'resultsUrl' => route('admin.surveys.results', $survey, absolute: false),
        ]);
    }

    public function edit(Survey $survey): Response
    {
        Gate::authorize('update', $survey);

        $survey->load('questions');

        return Inertia::render('admin/surveys/Edit', [
            'survey' => $this->surveyPayload($survey),
            'questionTypes' => SurveyQuestionType::values(),
            'updateUrl' => route('admin.surveys.update', $survey, absolute: false),
            'publishUrl' => route('admin.surveys.publish', $survey, absolute: false),
            'indexUrl' => route('admin.surveys.index', absolute: false),
        ]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);
        $this->ensureMutable($survey);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable', 'uuid'],
            'questions.*.order' => ['required', 'integer', 'min:1', 'max:1000'],
            'questions.*.type' => ['required', Rule::enum(SurveyQuestionType::class)],
            'questions.*.key' => ['required', 'string', 'max:120'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.help_text' => ['nullable', 'string', 'max:1000'],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.options' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($survey, $validated): void {
            $survey->forceFill([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ])->save();

            $kept = [];
            foreach ($validated['questions'] as $question) {
                $attributes = [
                    'order' => (int) $question['order'],
                    'type' => $question['type'],
                    'key' => $question['key'],
                    'prompt' => $question['prompt'],
                    'help_text' => $question['help_text'] ?? null,
                    'required' => (bool) $question['required'],
                    'options' => $question['options'] ?? null,
                ];

                if (isset($question['id'])) {
                    $model = SurveyQuestion::query()
                        ->where('survey_id', $survey->getKey())
                        ->whereKey($question['id'])
                        ->firstOrFail();
                    $model->forceFill($attributes)->save();
                } else {
                    $model = $survey->questions()->create($attributes);
                }

                $kept[] = (string) $model->getKey();
            }

            $survey->questions()->whereNotIn('id', $kept)->delete();
        });

        $this->audit->record('survey.updated', subject: $survey, actor: $request->user(), after: [
            'survey_id' => $survey->getKey(),
            'question_count' => count($validated['questions']),
        ]);

        return to_route('admin.surveys.edit', $survey)->with('status', 'survey-updated');
    }

    public function publish(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);
        $this->ensureMutable($survey);
        abort_if($survey->questions()->count() === 0, 422, 'Survey needs at least one question before publishing.');

        $user = $request->user();

        $survey->forceFill([
            'status' => SurveyStatus::Published->value,
            'published_at' => now(),
            'published_by_user_id' => $user instanceof User ? $user->getKey() : null,
        ])->save();

        $this->audit->record('survey.published', subject: $survey, actor: $user, after: [
            'survey_id' => $survey->getKey(),
            'published_at' => $survey->published_at?->toIso8601String(),
        ]);

        return to_route('admin.surveys.index')->with('status', 'survey-published');
    }

    public function archive(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('delete', $survey);

        $survey->forceFill([
            'status' => SurveyStatus::Archived->value,
            'archived_at' => now(),
        ])->save();

        $this->audit->record('survey.archived', subject: $survey, actor: $request->user(), after: [
            'survey_id' => $survey->getKey(),
            'archived_at' => $survey->archived_at?->toIso8601String(),
        ]);

        return to_route('admin.surveys.index')->with('status', 'survey-archived');
    }

    public function results(Survey $survey): Response
    {
        Gate::authorize('viewAny', Survey::class);

        $responses = SurveyResponse::query()
            ->with(['submittedBy', 'client', 'entrepreneurProfile'])
            ->where('survey_id', $survey->getKey())
            ->latest('submitted_at')
            ->limit(100)
            ->get();

        return Inertia::render('admin/surveys/Results', [
            'survey' => $this->surveyPayload($survey->load('questions')),
            'summary' => [
                'responses' => $responses->count(),
                'average_score' => $responses->whereNotNull('overall_score')->avg('overall_score'),
                'average_nps' => $responses->whereNotNull('nps_score')->avg('nps_score'),
            ],
            'responses' => $responses
                ->map(fn (SurveyResponse $response): array => [
                    'id' => $response->id,
                    'subject' => $response->client?->trading_name
                        ?: $response->client?->legal_name
                        ?: $response->entrepreneurProfile?->name
                        ?: 'Unknown',
                    'submitted_by' => $response->submittedBy?->name,
                    'submitted_at' => $response->submitted_at?->toIso8601String(),
                    'overall_score' => $response->overall_score,
                    'nps_score' => $response->nps_score,
                ])
                ->values(),
            'indexUrl' => route('admin.surveys.index', absolute: false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyPayload(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'key' => $survey->key,
            'version' => $survey->version,
            'title' => $survey->title,
            'description' => $survey->description,
            'status' => $survey->status?->value,
            'published_at' => $survey->published_at?->toIso8601String(),
            'questions' => $survey->questions
                ->map(fn (SurveyQuestion $question): array => [
                    'id' => $question->id,
                    'order' => $question->order,
                    'type' => $question->type?->value,
                    'key' => $question->key,
                    'prompt' => $question->prompt,
                    'help_text' => $question->help_text,
                    'required' => $question->required,
                    'options' => $question->options,
                ])
                ->values(),
        ];
    }

    private function ensureMutable(Survey $survey): void
    {
        if ($survey->status !== SurveyStatus::Draft) {
            throw ValidationException::withMessages([
                'survey' => 'Published or archived surveys are immutable.',
            ]);
        }
    }
}
