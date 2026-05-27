<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\NpoEngagementSubType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\NpoImpactMetricRecorder;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class NpoImpactMetricController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly NpoImpactMetricRecorder $metrics,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $client = $this->clients->resolveFor($request);
        $engagement = $this->currentEngagement($client);
        abort_unless($engagement instanceof NpoEngagement, 404);

        $validated = $request->validate([
            'metric_key' => ['nullable', 'string', 'max:120'],
            'metric_label' => ['required', 'string', 'max:160'],
            'value' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:40'],
            'platform_value' => ['nullable', 'numeric', 'min:0'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $metric = $this->metrics->record(
                $engagement,
                $validated,
                $request->user() instanceof User ? $request->user() : null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'value' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'metric' => $this->metrics->payload($metric),
        ], 201);
    }

    private function currentEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();
    }
}
