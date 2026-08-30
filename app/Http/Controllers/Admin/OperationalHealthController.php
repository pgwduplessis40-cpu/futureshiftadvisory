<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Services\OperationalHealth\OperationalHealthSchedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

final class OperationalHealthController extends Controller
{
    public function __construct(private readonly OperationalHealthSchedule $schedule) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $latestRun = OperationalHealthCheckRun::query()
            ->with(['results' => fn ($query) => $query->orderByRaw(
                "CASE status WHEN 'failed' THEN 0 WHEN 'warning' THEN 1 WHEN 'skipped' THEN 2 ELSE 3 END",
            )->latest()->orderBy('area')->orderBy('name')])
            ->latest('started_at')
            ->first();

        $results = $this->applyFilters(
            OperationalHealthCheckResult::query()->with('run'),
            $filters,
        )
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $results->through(fn (OperationalHealthCheckResult $result): array => $this->resultPayload($result));

        return Inertia::render('admin/app-health/Index', [
            'summary' => $this->summary($latestRun),
            'latestRun' => $latestRun instanceof OperationalHealthCheckRun
                ? $this->runPayload($latestRun, includeResults: true)
                : null,
            'recurringIssues' => $this->recurringIssues($latestRun),
            'results' => $results,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'area' => $filters['area'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'runUrl' => route('admin.app-health.run', absolute: false),
        ]);
    }

    public function run(): RedirectResponse
    {
        @set_time_limit(180);

        Artisan::call('fsa:operational-health-check', [
            '--without-alerts' => true,
        ]);

        return to_route('admin.app-health.index')
            ->with('status', 'operational-health-check-run');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($this->filled($filters, 'q')) {
            $term = (string) $filters['q'];
            $this->applyLikeAny($query, [
                'check_key',
                'name',
                'area',
                'url',
                'route_name',
                'actor_label',
                'workflow_subject_label',
                'issue_summary',
                'issue_detail',
                'fingerprint',
            ], $term);
        }

        if ($this->filled($filters, 'status')) {
            $query->where('status', (string) $filters['status']);
        }

        if ($this->filled($filters, 'area')) {
            $this->applyLikeAny($query, ['area'], (string) $filters['area']);
        }

        if ($this->filled($filters, 'date_from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['date_from'], $this->schedule->timezone())->startOfDay());
        }

        if ($this->filled($filters, 'date_to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['date_to'], $this->schedule->timezone())->endOfDay());
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applyLikeAny(Builder $query, array $columns, string $term): void
    {
        $needle = '%'.strtolower($term).'%';

        $query->where(function (Builder $inner) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw("LOWER(CAST({$column} AS TEXT)) LIKE ?", [$needle]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(?OperationalHealthCheckRun $latestRun): array
    {
        $latestIssue = $latestRun instanceof OperationalHealthCheckRun
            ? $latestRun->results->first(fn (OperationalHealthCheckResult $result): bool => $result->needsAttention())
            : null;

        return [
            'latest_status' => $latestRun?->status,
            'latest_started_at' => $latestRun?->started_at?->toIso8601String(),
            'latest_started_at_label' => $this->dateLabel($latestRun?->started_at),
            'total_checks' => $latestRun?->total_checks ?? 0,
            'passed_checks' => $latestRun?->passed_checks ?? 0,
            'warning_checks' => $latestRun?->warning_checks ?? 0,
            'failed_checks' => $latestRun?->failed_checks ?? 0,
            'skipped_checks' => $latestRun?->skipped_checks ?? 0,
            'latest_issue' => $latestIssue instanceof OperationalHealthCheckResult
                ? $this->resultPayload($latestIssue)
                : null,
            'schedule' => $this->scheduleSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleSummary(): array
    {
        $timezone = $this->schedule->timezone();
        $today = Carbon::now($timezone);
        $startOfDay = $today->copy()->startOfDay()->utc();
        $endOfDay = $today->copy()->endOfDay()->utc();
        $nowUtc = $today->copy()->utc();
        $runTimes = $this->schedule->timesFor($today);
        $dueRunTimes = $this->schedule->dueTimesFor($today);
        $nextRunAt = $this->schedule->nextRunAfter($today);

        return [
            'timezone' => $timezone,
            'today_label' => $today->format('d M Y'),
            'run_times' => $runTimes,
            'expected_runs_today' => count($runTimes),
            'due_runs_today' => count($dueRunTimes),
            'completed_runs_today' => OperationalHealthCheckRun::query()
                ->whereBetween('started_at', [$startOfDay, $endOfDay])
                ->count(),
            'completed_due_runs_today' => OperationalHealthCheckRun::query()
                ->whereBetween('started_at', [$startOfDay, $nowUtc])
                ->count(),
            'next_run_at_label' => $this->dateLabel($nextRunAt),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recurringIssues(?OperationalHealthCheckRun $latestRun): array
    {
        if (! $latestRun instanceof OperationalHealthCheckRun) {
            return [];
        }

        return $latestRun->results
            ->filter(fn (OperationalHealthCheckResult $result): bool => $result->needsAttention()
                && is_string($result->fingerprint)
                && $result->fingerprint !== '')
            ->unique('fingerprint')
            ->filter(fn (OperationalHealthCheckResult $result): bool => (int) $result->consecutive_failures > 1
                || (int) $result->failures_last_7_days > 1)
            ->take(8)
            ->map(fn (OperationalHealthCheckResult $result): array => $this->resultPayload($result))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(OperationalHealthCheckRun $run, bool $includeResults = false): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'environment' => $run->environment,
            'release_version' => $run->release_version,
            'duration_ms' => $run->duration_ms,
            'total_checks' => $run->total_checks,
            'passed_checks' => $run->passed_checks,
            'warning_checks' => $run->warning_checks,
            'failed_checks' => $run->failed_checks,
            'skipped_checks' => $run->skipped_checks,
            'started_at' => $run->started_at?->toIso8601String(),
            'started_at_label' => $this->dateLabel($run->started_at),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'finished_at_label' => $this->dateLabel($run->finished_at),
            'results' => $includeResults
                ? $run->results
                    ->map(fn (OperationalHealthCheckResult $result): array => $this->resultPayload($result))
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(OperationalHealthCheckResult $result): array
    {
        return [
            'id' => $result->id,
            'run_id' => $result->run_id,
            'run_started_at_label' => $this->dateLabel($result->run?->started_at),
            'check_key' => $result->check_key,
            'name' => $result->name,
            'area' => $result->area,
            'status' => $result->status,
            'method' => $result->method,
            'url' => $result->url,
            'route_name' => $result->route_name,
            'expected_statuses' => $result->expected_statuses ?? [],
            'actual_status' => $result->actual_status,
            'expected_content_type' => $result->expected_content_type,
            'actual_content_type' => $result->actual_content_type,
            'response_time_ms' => $result->response_time_ms,
            'actor_label' => $result->actor_label,
            'actor_role' => $result->actor_role,
            'workflow_subject_type' => $result->workflow_subject_type,
            'workflow_subject_id' => $result->workflow_subject_id,
            'workflow_subject_label' => $result->workflow_subject_label,
            'expected_behavior' => $result->expected_behavior,
            'issue_summary' => $result->issue_summary,
            'issue_detail' => $result->issue_detail,
            'fingerprint' => $result->fingerprint,
            'consecutive_failures' => $result->consecutive_failures,
            'failures_last_7_days' => $result->failures_last_7_days,
            'failures_last_30_days' => $result->failures_last_30_days,
            'first_seen_at_label' => $this->dateLabel($result->first_seen_at),
            'last_seen_at_label' => $this->dateLabel($result->last_seen_at),
            'exception_class' => $result->exception_class,
            'exception_message' => $result->exception_message,
            'context' => $result->context,
            'created_at' => $result->created_at?->toIso8601String(),
            'created_at_label' => $this->dateLabel($result->created_at),
        ];
    }

    private function dateLabel(?CarbonInterface $date): ?string
    {
        return $date?->copy()->timezone($this->schedule->timezone())->format('d M Y, g:i A');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filled(array $filters, string $key): bool
    {
        return isset($filters[$key]) && is_string($filters[$key]) && trim($filters[$key]) !== '';
    }
}
