<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdvisorApi;

use App\Http\Controllers\Controller;
use App\Models\AdvisorApiClient;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apiClient = $this->apiClient($request);
        $this->authorizeScope($apiClient, AdvisorApiClient::SCOPE_READ_CLIENTS);

        $clients = Client::query()
            ->whereIn('id', $apiClient->advisor->accessibleClientIds())
            ->orderBy('legal_name')
            ->limit(100)
            ->get()
            ->map(fn (Client $client): array => $this->payload($client));

        return response()->json(['data' => $clients]);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $apiClient = $this->apiClient($request);
        $this->authorizeScope($apiClient, AdvisorApiClient::SCOPE_READ_CLIENTS);
        $this->authorizeClient($apiClient, $client);

        return response()->json(['data' => $this->payload($client)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Client $client): array
    {
        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'engagement_type' => $client->engagement_type?->value,
            'status' => $client->status?->value,
            'data_quality' => $client->data_quality,
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }

    private function apiClient(Request $request): AdvisorApiClient
    {
        $client = $request->attributes->get('advisor_api_client');

        abort_unless($client instanceof AdvisorApiClient, 401);

        return $client;
    }

    private function authorizeScope(AdvisorApiClient $apiClient, string $scope): void
    {
        abort_unless($apiClient->allows($scope), 403, 'Advisor API scope is not allowed.');
    }

    private function authorizeClient(AdvisorApiClient $apiClient, Client $client): void
    {
        abort_unless(in_array((string) $client->id, $apiClient->advisor->accessibleClientIds(), true), 404);
    }
}
