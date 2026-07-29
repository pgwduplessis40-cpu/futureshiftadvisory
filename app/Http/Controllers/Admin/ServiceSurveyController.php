<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyType;
use App\Http\Controllers\Controller;
use App\Models\EntrepreneurProfile;
use App\Models\ServiceActivation;
use App\Models\Survey;
use App\Models\User;
use App\Services\Surveys\SurveyActivationService;
use App\Services\Surveys\SurveyLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceSurveyController extends Controller
{
    public function index(Request $request, SurveyLibrary $library): Response
    {
        Gate::authorize('viewAny', Survey::class);

        $user = $request->user();
        $library->ensureServiceImprovement($user instanceof User ? $user : null);

        return Inertia::render('admin/surveys/ServiceAssignments', [
            'surveys' => Survey::query()
                ->published()
                ->where('type', SurveyType::ServiceImprovement->value)
                ->latest('published_at')
                ->get(['id', 'title', 'version'])
                ->map(fn (Survey $survey): array => [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'version' => $survey->version,
                ])
                ->values(),
            'activations' => ServiceActivation::query()
                ->with(['client', 'package'])
                ->withCount([
                    'surveyAssignments as open_survey_count' => fn (Builder $query) => $query
                        ->whereIn('status', SurveyAssignmentStatus::activeValues()),
                ])
                ->where('status', ServiceActivation::STATUS_CLOSED)
                ->latest('closed_at')
                ->limit(100)
                ->get()
                ->map(function (ServiceActivation $activation): array {
                    $package = is_array($activation->selected_package_snapshot)
                        ? $activation->selected_package_snapshot
                        : [];
                    $packageLabel = data_get($package, 'client_label')
                        ?? data_get($package, 'package_name')
                        ?? $activation->package?->package_name;

                    return [
                        'id' => $activation->id,
                        'client_name' => $activation->client?->trading_name
                            ?: $activation->client?->legal_name
                            ?: 'Unknown client',
                        'service_label' => $activation->clientLabel(),
                        'package_label' => is_string($packageLabel) ? $packageLabel : null,
                        'closed_at' => $activation->closed_at?->toIso8601String(),
                        'has_open_survey' => $activation->open_survey_count > 0,
                        'issue_url' => route('admin.service-surveys.store', $activation, absolute: false),
                    ];
                })
                ->values(),
            'surveyIndexUrl' => route('admin.surveys.index', absolute: false),
        ]);
    }

    public function store(
        Request $request,
        ServiceActivation $serviceActivation,
        SurveyActivationService $activation,
    ): RedirectResponse {
        Gate::authorize('viewAny', Survey::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'survey_id' => ['required', 'uuid', Rule::exists('surveys', 'id')],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);

        $survey = Survey::query()
            ->published()
            ->where('type', SurveyType::ServiceImprovement->value)
            ->whereKey($validated['survey_id'])
            ->firstOrFail();

        $assignment = $activation->activateForService(
            $serviceActivation,
            $survey,
            $user,
            isset($validated['due_at']) ? Carbon::parse($validated['due_at']) : null,
        );

        return back()
            ->with('status', 'service-survey-activated')
            ->with('survey_assignment_id', $assignment->getKey());
    }

    public function storeForEntrepreneur(
        Request $request,
        EntrepreneurProfile $entrepreneurProfile,
        SurveyActivationService $activation,
    ): RedirectResponse {
        Gate::authorize('view', $entrepreneurProfile);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $survey = Survey::query()
            ->published()
            ->where('type', SurveyType::ServiceImprovement->value)
            ->latest('published_at')
            ->first();

        if (! $survey instanceof Survey) {
            throw ValidationException::withMessages([
                'survey' => 'Publish a service improvement survey before issuing it.',
            ]);
        }

        $assignment = $activation->activateForEntrepreneurService($entrepreneurProfile, $survey, $user);

        return back()
            ->with('status', 'service-survey-activated')
            ->with('survey_assignment_id', $assignment->getKey());
    }
}
