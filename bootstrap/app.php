<?php

use App\Console\Commands\AggregateIntegrationHealth;
use App\Console\Commands\VerifyAuditChain;
use App\Http\Middleware\EnforceClientScope;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogAuditEvent;
use App\Http\Middleware\RequireMfa;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // EnforceClientScope must run on every authenticated route so the
        // Postgres session variables driving row-level security policies
        // are always set. See PLAN.md section 6.2 and 7.4.
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnforceClientScope::class,
        ]);

        $middleware->api(append: [
            EnforceClientScope::class,
        ]);

        // Aliased so individual routes can opt in to read-tracking
        // (e.g. ->middleware('audit.read:document.downloaded') on a
        // sensitive endpoint). See PLAN.md section 7.3.
        $middleware->alias([
            'audit.read' => LogAuditEvent::class,
            'mfa' => RequireMfa::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Daily audit chain integrity check. Failures are surfaced via
        // process exit code today; once notifications land (WO-12) the
        // command will also notify super-admins.
        $schedule->command(VerifyAuditChain::class)
            ->dailyAt('02:30')
            ->name('fsa-audit-verify')
            ->withoutOverlapping();

        $schedule->command(AggregateIntegrationHealth::class)
            ->everyFiveMinutes()
            ->name('fsa-integration-health-aggregate')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
