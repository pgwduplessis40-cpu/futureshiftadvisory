<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Survey;
use App\Services\Surveys\SurveyResultAggregator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SurveyResultController extends Controller
{
    public function client(Client $client, SurveyResultAggregator $aggregator): Response
    {
        Gate::authorize('view', $client);

        return Inertia::render('advisor/surveys/Results', [
            'subject' => [
                'type' => 'client',
                'name' => $client->trading_name ?: $client->legal_name,
                'back_url' => route('advisor.clients.show', $client, absolute: false),
                'activation_url' => route('advisor.clients.survey-assignments.store', $client, absolute: false),
            ],
            'surveys' => Survey::query()
                ->published()
                ->latest('published_at')
                ->get(['id', 'title', 'version'])
                ->map(fn (Survey $survey): array => $survey->only(['id', 'title', 'version']))
                ->values(),
            'results' => $aggregator->forClient($client),
        ]);
    }

    public function entrepreneur(EntrepreneurProfile $entrepreneurProfile, SurveyResultAggregator $aggregator): Response
    {
        Gate::authorize('view', $entrepreneurProfile);

        return Inertia::render('advisor/surveys/Results', [
            'subject' => [
                'type' => 'entrepreneur',
                'name' => $entrepreneurProfile->name,
                'back_url' => route('advisor.entrepreneurs.show', $entrepreneurProfile, absolute: false),
                'activation_url' => route('advisor.entrepreneurs.survey-assignments.store', $entrepreneurProfile, absolute: false),
            ],
            'surveys' => Survey::query()
                ->published()
                ->latest('published_at')
                ->get(['id', 'title', 'version'])
                ->map(fn (Survey $survey): array => $survey->only(['id', 'title', 'version']))
                ->values(),
            'results' => $aggregator->forEntrepreneur($entrepreneurProfile),
        ]);
    }
}
