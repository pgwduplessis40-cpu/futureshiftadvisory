<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationHealthAlert;
use App\Models\IntegrationHealthSample;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationHealthController extends Controller
{
    public function __invoke(): Response
    {
        $samples = IntegrationHealthSample::query()
            ->latest('window_end')
            ->limit(200)
            ->get()
            ->unique('service')
            ->values();

        return Inertia::render('admin/integration-health/Index', [
            'summary' => $this->summary($samples),
            'services' => $samples
                ->map(fn (IntegrationHealthSample $sample): array => $this->servicePayload($sample))
                ->values(),
            'recentAlerts' => IntegrationHealthAlert::query()
                ->latest('notified_at')
                ->limit(10)
                ->get()
                ->map(fn (IntegrationHealthAlert $alert): array => [
                    'id' => $alert->id,
                    'service' => $alert->service,
                    'stuck_started_at' => $alert->stuck_started_at?->toIso8601String(),
                    'last_red_window_end' => $alert->last_red_window_end?->toIso8601String(),
                    'notified_at' => $alert->notified_at?->toIso8601String(),
                ])
                ->values(),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  Collection<int, IntegrationHealthSample>  $samples
     * @return array<string, int>
     */
    private function summary(Collection $samples): array
    {
        return [
            'total' => $samples->count(),
            'green' => $samples->where('health', IntegrationHealthSample::HEALTH_GREEN)->count(),
            'amber' => $samples->where('health', IntegrationHealthSample::HEALTH_AMBER)->count(),
            'red' => $samples->where('health', IntegrationHealthSample::HEALTH_RED)->count(),
            'stale' => $samples->filter(fn (IntegrationHealthSample $sample): bool => $this->isStale($sample))->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(IntegrationHealthSample $sample): array
    {
        $lagSeconds = $sample->window_end === null
            ? null
            : (int) max(0, $sample->window_end->diffInSeconds(now(), false));

        return [
            'id' => $sample->id,
            'service' => $sample->service,
            'health' => $sample->health,
            'success_rate' => $sample->success_rate,
            'p95_latency_ms' => $sample->p95_latency_ms,
            'window_start' => $sample->window_start?->toIso8601String(),
            'window_end' => $sample->window_end?->toIso8601String(),
            'lag_seconds' => $lagSeconds,
            'fresh' => ! $this->isStale($sample),
        ];
    }

    private function isStale(IntegrationHealthSample $sample): bool
    {
        return $sample->window_end === null || $sample->window_end->lt(now()->subMinutes(5));
    }
}
