<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\AggregateIntegrationHealth;
use App\Http\Controllers\Controller;
use App\Models\AiUsageEvent;
use App\Models\IntegrationHealthAlert;
use App\Models\IntegrationHealthSample;
use App\Services\Integration\IntegrationCredentials;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationHealthController extends Controller
{
    public function __construct(private readonly IntegrationCredentials $credentials) {}

    public function index(): Response
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
            'aiUsage' => $this->aiUsagePayload(),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    public function refresh(): RedirectResponse
    {
        Artisan::call(AggregateIntegrationHealth::class, [
            '--minutes' => 5,
        ]);

        return to_route('admin.integration-health.index')
            ->with('status', 'integration-health-refreshed');
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

    /**
     * @return array<string, mixed>
     */
    private function aiUsagePayload(): array
    {
        $now = now();
        $today = $this->usagePeriod($now->copy()->startOfDay(), $now);
        $month = $this->usagePeriod($now->copy()->startOfMonth(), $now);
        $budgetUsd = config('ai.costs.monthly_budget_usd');
        $usdToNzdRate = config('ai.costs.usd_to_nzd_rate');

        return [
            'today' => $today,
            'month' => $month,
            'budget' => $this->budgetPayload($month['estimated_cost_usd'], is_numeric($budgetUsd) ? (float) $budgetUsd : null),
            'breakdown' => $this->modelBreakdown($now->copy()->startOfMonth(), $now),
            'currency' => [
                'base' => 'USD',
                'nzd_rate' => is_numeric($usdToNzdRate) ? (float) $usdToNzdRate : null,
                'today_estimated_cost_nzd' => $this->toNzd($today['estimated_cost_usd']),
                'month_estimated_cost_nzd' => $this->toNzd($month['estimated_cost_usd']),
            ],
            'official' => [
                'configured' => $this->credentials->present('anthropic_admin', 'key'),
                'status' => $this->credentials->present('anthropic_admin', 'key')
                    ? 'ready_for_usage_cost_sync'
                    : 'admin_api_key_missing',
                'month_cost_usd' => null,
                'last_synced_at' => null,
            ],
            'pricing' => [
                'basis' => 'local_estimate_from_response_tokens',
                'provider' => 'anthropic',
            ],
        ];
    }

    /**
     * @return array{requests:int,input_tokens:int,output_tokens:int,total_tokens:int,estimated_cost_usd:float}
     */
    private function usagePeriod(CarbonInterface $start, CarbonInterface $end): array
    {
        if (! $this->aiUsageStoreAvailable()) {
            return $this->emptyUsagePeriod();
        }

        $row = AiUsageEvent::query()
            ->where('provider', 'anthropic')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end)
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_usd), 0) as estimated_cost_usd')
            ->first();

        $inputTokens = (int) ($row?->input_tokens ?? 0);
        $outputTokens = (int) ($row?->output_tokens ?? 0);

        return [
            'requests' => (int) ($row?->requests ?? 0),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'estimated_cost_usd' => round((float) ($row?->estimated_cost_usd ?? 0), 6),
        ];
    }

    /**
     * @return array{requests:int,input_tokens:int,output_tokens:int,total_tokens:int,estimated_cost_usd:float}
     */
    private function emptyUsagePeriod(): array
    {
        return [
            'requests' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0.0,
        ];
    }

    /**
     * @return array<int, array{model:string,requests:int,input_tokens:int,output_tokens:int,total_tokens:int,estimated_cost_usd:float}>
     */
    private function modelBreakdown(CarbonInterface $start, CarbonInterface $end): array
    {
        if (! $this->aiUsageStoreAvailable()) {
            return [];
        }

        return AiUsageEvent::query()
            ->where('provider', 'anthropic')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end)
            ->select('model')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_usd), 0) as estimated_cost_usd')
            ->groupBy('model')
            ->orderByRaw('COALESCE(SUM(estimated_cost_usd), 0) desc')
            ->limit(6)
            ->get()
            ->map(function (AiUsageEvent $event): array {
                $inputTokens = (int) $event->input_tokens;
                $outputTokens = (int) $event->output_tokens;

                return [
                    'model' => $event->model,
                    'requests' => (int) $event->requests,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'estimated_cost_usd' => round((float) $event->estimated_cost_usd, 6),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{monthly_budget_usd:float|null,remaining_usd:float|null,percent_used:float|null,status:string}
     */
    private function budgetPayload(float $monthCostUsd, ?float $budgetUsd): array
    {
        if ($budgetUsd === null || $budgetUsd <= 0) {
            return [
                'monthly_budget_usd' => null,
                'remaining_usd' => null,
                'percent_used' => null,
                'status' => 'not_set',
            ];
        }

        return [
            'monthly_budget_usd' => $budgetUsd,
            'remaining_usd' => round($budgetUsd - $monthCostUsd, 6),
            'percent_used' => round($monthCostUsd / $budgetUsd, 4),
            'status' => $monthCostUsd > $budgetUsd ? 'exceeded' : 'within_budget',
        ];
    }

    private function toNzd(float $usd): ?float
    {
        $rate = config('ai.costs.usd_to_nzd_rate');
        if (! is_numeric($rate) || (float) $rate <= 0) {
            return null;
        }

        return round($usd * (float) $rate, 6);
    }

    private function aiUsageStoreAvailable(): bool
    {
        return Schema::hasTable('ai_usage_events');
    }
}
