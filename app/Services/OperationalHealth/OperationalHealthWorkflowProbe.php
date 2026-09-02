<?php

declare(strict_types=1);

namespace App\Services\OperationalHealth;

use App\Enums\ReportType;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds probes that cross an HTTP edge or verify workflow contracts.
 *
 * Keeping these probes separate lets the runner focus on result recording and
 * protects its structural boundary as new workflow checks are introduced.
 *
 * @phpstan-type CheckDefinition array{
 *     key: string,
 *     name: string,
 *     area: string,
 *     method: string,
 *     url: ?string,
 *     route_name: string,
 *     user: ?User,
 *     expected_statuses: list<int>,
 *     expected_headers?: array<string, string>,
 *     expected_behavior: string,
 *     missing_fixture: ?string,
 *     sentinel?: bool,
 *     kind: 'external_http'|'route_contract',
 *     subject?: ?array{type: 'report', id: string, label: ?string}
 * }
 * @phpstan-type WorkflowProbe array{
 *     status: ?int,
 *     duration_ms: int,
 *     content_type: ?string,
 *     redirect_url: ?string,
 *     body_excerpt: ?string,
 *     fallback_pdf_detected: bool,
 *     exception_class: ?class-string<Throwable>,
 *     exception_message: ?string,
 *     headers: array<string, string>
 * }
 */
final class OperationalHealthWorkflowProbe
{
    /**
     * @return array<int, int>
     */
    public function deploymentExpectedStatuses(): array
    {
        return $this->requiresVerifiedDeployment()
            ? [200]
            : [200, 503];
    }

    public function requiresVerifiedDeployment(): bool
    {
        return (bool) config('operational_health.require_verified_deployment', false);
    }

    /**
     * @return list<CheckDefinition>
     */
    public function externalEdgeDefinitions(): array
    {
        if (! (bool) config('operational_health.external_edge_enabled', false)) {
            return [];
        }

        $baseUrl = rtrim(trim((string) config('operational_health.external_edge_url', '')), '/');

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return [[
                'key' => 'public.edge.login',
                'name' => 'Public login route via nginx',
                'area' => 'Public edge',
                'method' => 'GET',
                'url' => null,
                'route_name' => 'login',
                'user' => null,
                'expected_statuses' => [200],
                'expected_behavior' => 'The public login route should be reachable through the configured nginx edge.',
                'missing_fixture' => null,
                'sentinel' => true,
                'kind' => 'external_http',
            ]];
        }

        return [[
            'key' => 'public.edge.login',
            'name' => 'Public login route via nginx',
            'area' => 'Public edge',
            'method' => 'GET',
            'url' => $baseUrl.'/login',
            'route_name' => 'login',
            'user' => null,
            'expected_statuses' => [200],
            'expected_behavior' => 'The public login route should be reachable through the configured nginx edge, rather than only through Laravel’s in-process kernel.',
            'missing_fixture' => null,
            'sentinel' => true,
            'kind' => 'external_http',
        ]];
    }

    /**
     * @return array<int, string>
     */
    public function pendingMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return ['migrations_table_missing'];
        }

        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);
        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $ran = DB::table('migrations')
            ->pluck('migration')
            ->map(fn (mixed $migration): string => (string) $migration)
            ->all();

        return array_values(array_diff($files, $ran));
    }

    /**
     * @return CheckDefinition
     */
    public function dueDiligenceFeedbackDefinition(?Client $client, ?User $advisor): array
    {
        $report = $this->dueDiligenceReportCandidate($client);

        return [
            'key' => 'advisor.dd_client.feedback',
            'name' => 'Advisor DD feedback route',
            'area' => 'Advisor workflow',
            'method' => 'PATCH',
            'url' => $report instanceof Report
                ? route('advisor.reports.dd-feedback', $report, absolute: false)
                : null,
            'route_name' => 'advisor.reports.dd-feedback',
            'user' => $advisor,
            'expected_statuses' => [200],
            'expected_headers' => ['allow' => 'patch'],
            'expected_behavior' => 'The DD feedback endpoint must remain registered for PATCH rather than returning a missing or stale route. This route-contract check is deliberately zero-write, so it cannot alter a report or send a client message.',
            'missing_fixture' => 'No reviewed due-diligence report monitor fixture is available.',
            'kind' => 'route_contract',
            'subject' => $report instanceof Report ? [
                'type' => 'report',
                'id' => (string) $report->getKey(),
                'label' => $report->title,
            ] : null,
        ];
    }

    /**
     * @return WorkflowProbe
     */
    public function dispatchExternalRequest(string $method, string $url): array
    {
        $started = hrtime(true);

        try {
            $response = Http::accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                ->withUserAgent('FutureShiftAdvisory OperationalHealthEdgeCheck/1.0')
                ->timeout(20)
                ->send(strtoupper($method), $url);
            $headers = [];

            foreach ($response->headers() as $name => $values) {
                $headers[strtolower((string) $name)] = implode(', ', array_map('strval', (array) $values));
            }

            return [
                'status' => $response->status(),
                'duration_ms' => $this->elapsedMs($started),
                'content_type' => $response->header('Content-Type'),
                'redirect_url' => $response->header('Location'),
                'body_excerpt' => $this->bodyExcerpt($response->body()),
                'fallback_pdf_detected' => false,
                'exception_class' => null,
                'exception_message' => null,
                'headers' => $headers,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => null,
                'duration_ms' => $this->elapsedMs($started),
                'content_type' => null,
                'redirect_url' => null,
                'body_excerpt' => null,
                'fallback_pdf_detected' => false,
                'exception_class' => $exception::class,
                'exception_message' => Str::limit($exception->getMessage(), 500, ''),
                'headers' => [],
            ];
        }
    }

    /**
     * @return WorkflowProbe
     */
    public function routeContractProbe(string $routeName, string $method): array
    {
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return $this->routeContractResult(404, []);
        }

        $methods = array_map('strtoupper', $route->methods());

        return $this->routeContractResult(
            in_array(strtoupper($method), $methods, true) ? 200 : 405,
            ['allow' => implode(', ', $methods)],
        );
    }

    private function dueDiligenceReportCandidate(?Client $client): ?Report
    {
        if (! $client instanceof Client || ! Schema::hasTable('reports')) {
            return null;
        }

        return Report::query()
            ->where('client_id', $client->getKey())
            ->whereIn('type', [
                ReportType::DueDiligence->value,
                ReportType::AcquisitionGoNoGo->value,
            ])
            ->where('review_status', 'reviewed')
            ->latest('generated_at')
            ->first();
    }

    /**
     * @param  array<string, string>  $headers
     * @return WorkflowProbe
     */
    private function routeContractResult(int $status, array $headers): array
    {
        return [
            'status' => $status,
            'duration_ms' => 0,
            'content_type' => null,
            'redirect_url' => null,
            'body_excerpt' => null,
            'fallback_pdf_detected' => false,
            'exception_class' => null,
            'exception_message' => null,
            'headers' => $headers,
        ];
    }

    private function elapsedMs(int $started): int
    {
        return (int) max(0, round((hrtime(true) - $started) / 1_000_000));
    }

    private function bodyExcerpt(string $content): ?string
    {
        $content = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $content);
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));

        return $text === '' ? null : Str::limit($text, 500, '');
    }
}
