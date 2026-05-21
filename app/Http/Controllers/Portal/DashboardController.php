<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly OnboardingWizard $wizard,
    ) {}

    public function __invoke(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);

        return Inertia::render('portal/Dashboard', [
            'client' => $this->clientPayload($client),
            'progress' => $this->wizard->progress($client),
            'currentStep' => $this->wizard->currentStepSlug($client),
            'onboardingUrl' => route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ]),
            'notificationSummary' => [
                'unread' => 0,
                'urgent' => 0,
            ],
            'messagesUrl' => '#messages-phase-1',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'data_quality' => $client->data_quality,
            'nzbn' => $client->nzbn,
        ];
    }
}
