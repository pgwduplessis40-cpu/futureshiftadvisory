<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Journeys\ServiceJourney;
use App\Services\Journeys\ServiceJourneyPrograms;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ServiceJourneyController extends Controller
{
    public function __construct(private readonly ClientPortalResolver $clients) {}

    public function preference(Request $request, ServiceJourney $journeys, AuditWriter $audit): RedirectResponse
    {
        $participant = $request->user();
        abort_unless($participant instanceof User, 403);

        $validated = $request->validate([
            'service_key' => ['required', 'string', 'max:80'],
            'recognition_enabled' => ['required', 'boolean'],
        ]);
        $client = $this->clients->resolveFor($request);
        $serviceKey = ServiceJourneyPrograms::normalise((string) $validated['service_key']);
        $this->assertAccessibleService($client, $serviceKey);
        $enabled = (bool) $validated['recognition_enabled'];
        $enrollment = $journeys->setRecognition($client, $participant, $serviceKey, $enabled);

        $audit->record($enabled ? 'service_journey.recognition_enabled' : 'service_journey.recognition_disabled', subject: $enrollment, actor: $participant, after: [
            'client_id' => $client->getKey(),
            'service_key' => $serviceKey,
            'program_version' => $enrollment->program_version,
        ]);

        return to_route('portal.dashboard', ['client' => $client->getKey()])
            ->with('status', $enabled ? 'service-journey-recognition-enabled' : 'service-journey-recognition-disabled');
    }

    public function seen(Request $request, ServiceJourney $journeys): RedirectResponse
    {
        $participant = $request->user();
        abort_unless($participant instanceof User, 403);

        $validated = $request->validate([
            'service_key' => ['required', 'string', 'max:80'],
        ]);
        $client = $this->clients->resolveFor($request);
        $this->assertAccessibleService($client, (string) $validated['service_key']);

        $journeys->markSeen($client, $participant, (string) $validated['service_key']);

        return to_route('portal.dashboard', ['client' => $client->getKey()])
            ->with('status', 'service-journey-badges-seen');
    }

    private function assertAccessibleService(Client $client, string $requestedServiceKey): void
    {
        $requestedServiceKey = ServiceJourneyPrograms::normalise($requestedServiceKey);
        $primaryServiceKey = ServiceJourneyPrograms::normalise($client->engagement_type->value);

        if ($primaryServiceKey === $requestedServiceKey) {
            return;
        }

        $activationServiceTypes = [$requestedServiceKey];
        if ($requestedServiceKey === ServiceJourneyPrograms::normalise(ServiceActivation::SERVICE_ENTREPRENEUR)) {
            $activationServiceTypes[] = ServiceActivation::SERVICE_ENTREPRENEUR;
        }

        if (ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereIn('service_type', $activationServiceTypes)
            ->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'service_key' => 'Journey recognition is only available for your current active service workspace.',
        ]);
    }
}
