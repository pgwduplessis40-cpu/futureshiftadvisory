<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Performance\DashboardLaunchTiming;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeasureDashboardLaunch
{
    public function __construct(private readonly DashboardLaunchTiming $timing) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMeasure($request)) {
            return $next($request);
        }

        $this->timing->start();

        $response = $next($request);
        $metrics = $this->timing->metrics();
        $serverTiming = sprintf(
            'app;dur=%.1f, db;dur=%.1f, db-count;dur=%d',
            $metrics['app_ms'],
            $metrics['db_ms'],
            $metrics['db_count'],
        );

        $response->headers->set('X-FSA-Launch-App-Ms', (string) $metrics['app_ms']);
        $response->headers->set('X-FSA-Launch-DB-Ms', (string) $metrics['db_ms']);
        $response->headers->set('X-FSA-Launch-DB-Queries', (string) $metrics['db_count']);

        if ($response->headers->has('Server-Timing')) {
            $serverTiming = $response->headers->get('Server-Timing').', '.$serverTiming;
        }

        $response->headers->set('Server-Timing', $serverTiming);

        return $response;
    }

    private function shouldMeasure(Request $request): bool
    {
        return (bool) config('performance.dashboard_launch_timing', false)
            && $request->routeIs(
                'dashboard',
                'portal.dashboard',
                'portal.entrepreneur.dashboard',
                'portal.npo-board.dashboard',
            );
    }
}
