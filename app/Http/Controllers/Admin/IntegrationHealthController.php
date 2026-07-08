<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\AggregateIntegrationHealth;
use App\Http\Controllers\Controller;
use App\Models\AiUsageEvent;
use App\Models\IntegrationCall;
use App\Models\IntegrationHealthAlert;
use App\Models\IntegrationHealthSample;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\IntegrationRegistry;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class IntegrationHealthController extends Controller
{
    public function __construct(
        private readonly IntegrationCredentials $credentials,
        private readonly IntegrationRegistry $registry,
    ) {}

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
            'governanceNotices' => $this->governanceNotices(),
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
     * @return array<int, array{service:string,label:string,note:string}>
     */
    private function governanceNotices(): array
    {
        return $this->registry->all()
            ->filter(fn (array $integration): bool => is_string($integration['availability_status'] ?? null))
            ->map(fn (array $integration): array => [
                'service' => (string) ($integration['display_name'] ?? $integration['integration_key']),
                'label' => (string) ($integration['availability_label'] ?? $integration['availability_status']),
                'note' => (string) ($integration['availability_note'] ?? ''),
            ])
            ->values()
            ->all();
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
            'official' => $this->anthropicOfficialCostPayload($now),
            'provider_attempts' => [
                'today' => $this->anthropicAttemptPeriod($now->copy()->startOfDay(), $now),
                'month' => $this->anthropicAttemptPeriod($now->copy()->startOfMonth(), $now),
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

    /**
     * @return array{attempts:int,successes:int,retries:int,failures:int,latest_error:string|null,latest_at:string|null}
     */
    private function anthropicAttemptPeriod(CarbonInterface $start, CarbonInterface $end): array
    {
        if (! Schema::hasTable('integration_calls')) {
            return [
                'attempts' => 0,
                'successes' => 0,
                'retries' => 0,
                'failures' => 0,
                'latest_error' => null,
                'latest_at' => null,
            ];
        }

        $attemptStatuses = [
            IntegrationCall::STATUS_SUCCESS,
            IntegrationCall::STATUS_RETRY,
            IntegrationCall::STATUS_FAILURE,
        ];

        $base = IntegrationCall::query()
            ->where('service', 'anthropic')
            ->whereIn('status', $attemptStatuses)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end);

        /** @var IntegrationCall|null $latestFailure */
        $latestFailure = (clone $base)
            ->whereIn('status', [IntegrationCall::STATUS_RETRY, IntegrationCall::STATUS_FAILURE])
            ->latest('occurred_at')
            ->first();

        return [
            'attempts' => (clone $base)->count(),
            'successes' => (clone $base)->where('status', IntegrationCall::STATUS_SUCCESS)->count(),
            'retries' => (clone $base)->where('status', IntegrationCall::STATUS_RETRY)->count(),
            'failures' => (clone $base)->where('status', IntegrationCall::STATUS_FAILURE)->count(),
            'latest_error' => $this->integrationFailureSummary($latestFailure),
            'latest_at' => $latestFailure?->occurred_at?->toIso8601String(),
        ];
    }

    private function integrationFailureSummary(?IntegrationCall $call): ?string
    {
        if (! $call instanceof IntegrationCall || ! is_array($call->error_payload)) {
            return null;
        }

        $status = data_get($call->error_payload, 'http_status');
        $reason = data_get($call->error_payload, 'reason');
        $message = data_get($call->error_payload, 'message');

        $bodyMessage = null;
        $body = data_get($call->error_payload, 'body');
        if (is_string($body) && trim($body) !== '') {
            $decoded = json_decode($body, true);
            $candidate = is_array($decoded)
                ? data_get($decoded, 'error.message') ?? data_get($decoded, 'message')
                : null;
            $bodyMessage = is_scalar($candidate) ? trim((string) $candidate) : null;
        }

        $parts = [];
        if (is_numeric($status)) {
            $parts[] = 'HTTP '.((int) $status);
        }
        foreach ([$reason, $message, $bodyMessage] as $part) {
            if (is_scalar($part) && trim((string) $part) !== '') {
                $parts[] = trim((string) $part);
                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode(': ', $parts), 160, '');
    }

    /**
     * @return array{configured:bool,status:string,month_cost_usd:float|null,last_synced_at:string|null,error:string|null,credit_balance_supported:bool,credit_balance_usd:null}
     */
    private function anthropicOfficialCostPayload(CarbonInterface $now): array
    {
        $key = $this->credentials->get('anthropic_admin', 'key');

        if (! is_string($key) || trim($key) === '') {
            return $this->emptyAnthropicOfficialPayload('admin_api_key_missing');
        }

        $key = trim($key);
        if (! str_starts_with($key, 'sk-ant-admin')) {
            return $this->emptyAnthropicOfficialPayload('invalid_admin_api_key');
        }

        $endpoint = 'https://api.anthropic.com/v1/organizations/cost_report';

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withHeaders([
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $key,
                    'User-Agent' => 'FutureShiftAdvisory/1.0 (https://futureshiftadvisory.nz)',
                ])
                ->get($endpoint, [
                    'starting_at' => $now->copy()->utc()->startOfMonth()->toIso8601ZuluString(),
                    'ending_at' => $now->copy()->utc()->toIso8601ZuluString(),
                    'bucket_width' => '1d',
                    'limit' => 31,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyAnthropicOfficialPayload('sync_failed', 'Unable to reach Anthropic Admin API.');
        }

        if ($response->failed()) {
            return $this->emptyAnthropicOfficialPayload(
                'sync_failed',
                'Anthropic Admin API returned HTTP '.$response->status().'.',
            );
        }

        return [
            'configured' => true,
            'status' => 'synced',
            'month_cost_usd' => $this->sumAnthropicCostUsd($response->json()),
            'last_synced_at' => now()->toIso8601String(),
            'error' => null,
            'credit_balance_supported' => false,
            'credit_balance_usd' => null,
        ];
    }

    /**
     * @return array{configured:bool,status:string,month_cost_usd:null,last_synced_at:null,error:string|null,credit_balance_supported:bool,credit_balance_usd:null}
     */
    private function emptyAnthropicOfficialPayload(string $status, ?string $error = null): array
    {
        return [
            'configured' => $status !== 'admin_api_key_missing',
            'status' => $status,
            'month_cost_usd' => null,
            'last_synced_at' => null,
            'error' => $error,
            'credit_balance_supported' => false,
            'credit_balance_usd' => null,
        ];
    }

    private function sumAnthropicCostUsd(mixed $payload): float
    {
        if (! is_array($payload)) {
            return 0.0;
        }

        $cents = collect($payload['data'] ?? [])
            ->filter(fn (mixed $bucket): bool => is_array($bucket))
            ->flatMap(fn (array $bucket): array => is_array($bucket['results'] ?? null) ? $bucket['results'] : [])
            ->filter(fn (mixed $result): bool => is_array($result))
            ->sum(function (array $result): float {
                $amount = $result['amount'] ?? 0;

                return is_numeric($amount) ? (float) $amount : 0.0;
            });

        return round($cents / 100, 6);
    }
}
