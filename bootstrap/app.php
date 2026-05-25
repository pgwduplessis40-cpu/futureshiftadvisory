<?php

use App\Console\Commands\AggregateIntegrationHealth;
use App\Console\Commands\AlertStuckRedIntegrations;
use App\Console\Commands\CreatePracticeHealthSnapshots;
use App\Console\Commands\ExpireProposals;
use App\Console\Commands\GenerateMonthlyIndustryBriefings;
use App\Console\Commands\GeneratePreMeetingBriefs;
use App\Console\Commands\ProcessScheduledPayments;
use App\Console\Commands\RefreshEconomicIndicators;
use App\Console\Commands\RefreshValuationMultiples;
use App\Console\Commands\ReverifyBrokerFspRegistrations;
use App\Console\Commands\RunActiveLayerEngine;
use App\Console\Commands\RunBiasMonitor;
use App\Console\Commands\RunCoachSignalCalibrationLayer;
use App\Console\Commands\RunFeedbackLearningLayer;
use App\Console\Commands\RunFinancialMonitoring;
use App\Console\Commands\RunFunnelAnalyticsLayer;
use App\Console\Commands\RunQuestionnaireOptimisationLayer;
use App\Console\Commands\SendReengagementReminders;
use App\Console\Commands\SendWellbeingCheckinPrompts;
use App\Console\Commands\VerifyAuditChain;
use App\Http\Middleware\EnforceClientScope;
use App\Http\Middleware\EnforceSessionSecurity;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogAuditEvent;
use App\Http\Middleware\RequireAcceptedTerms;
use App\Http\Middleware\RequireMfa;
use App\Jobs\DispatchDailyDigest;
use App\Jobs\DispatchWeeklyDigest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            EnforceSessionSecurity::class,
            RequireAcceptedTerms::class,
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

        $schedule->command(AlertStuckRedIntegrations::class)
            ->everyFiveMinutes()
            ->name('fsa-integration-health-stuck-red-alerts')
            ->withoutOverlapping();

        $schedule->command(RunFeedbackLearningLayer::class)
            ->dailyAt('03:00')
            ->name('fsa-analysis-feedback-learning-layer')
            ->withoutOverlapping();

        $schedule->command(RunBiasMonitor::class)
            ->dailyAt('03:15')
            ->name('fsa-analysis-bias-monitor')
            ->withoutOverlapping();

        $schedule->command(RefreshEconomicIndicators::class)
            ->dailyAt('03:30')
            ->name('fsa-economic-indicators-refresh')
            ->withoutOverlapping();

        $schedule->command(RefreshValuationMultiples::class)
            ->cron('0 4 1 1,4,7,10 *')
            ->name('fsa-valuation-multiples-refresh')
            ->withoutOverlapping();

        if ((bool) env('FEATURE_CONTINUOUS_MONITORING', false)) {
            $schedule->command(RunFinancialMonitoring::class, ['--cadence' => 'daily'])
                ->dailyAt('04:00')
                ->name('fsa-financial-monitoring-daily')
                ->withoutOverlapping();

            $schedule->command(RunFinancialMonitoring::class, ['--cadence' => 'weekly'])
                ->weeklyOn(1, '04:30')
                ->name('fsa-financial-monitoring-weekly')
                ->withoutOverlapping();
        }

        $schedule->job(new DispatchDailyDigest)
            ->dailyAt('17:00')
            ->name('fsa-notifications-daily-digest')
            ->withoutOverlapping();

        $schedule->job(new DispatchWeeklyDigest)
            ->weeklyOn(1, '08:00')
            ->name('fsa-notifications-weekly-digest')
            ->withoutOverlapping();

        $schedule->command(SendWellbeingCheckinPrompts::class)
            ->monthlyOn(1, '09:00')
            ->name('fsa-wellbeing-monthly-prompts')
            ->withoutOverlapping();

        $schedule->command(SendReengagementReminders::class)
            ->dailyAt('09:30')
            ->name('fsa-offboarding-reengagement-reminders')
            ->withoutOverlapping();

        $schedule->command(ExpireProposals::class)
            ->dailyAt('00:20')
            ->name('fsa-proposals-expire')
            ->withoutOverlapping();

        $schedule->command(GenerateMonthlyIndustryBriefings::class)
            ->monthlyOn(1, '08:30')
            ->name('fsa-industry-briefings-monthly')
            ->withoutOverlapping();

        $schedule->command(GeneratePreMeetingBriefs::class)
            ->hourly()
            ->name('fsa-pre-meeting-briefs')
            ->withoutOverlapping();

        $schedule->command(RunFunnelAnalyticsLayer::class)
            ->monthlyOn(1, '03:45')
            ->name('fsa-funnel-analytics-learning-layer')
            ->withoutOverlapping();

        $schedule->command(CreatePracticeHealthSnapshots::class, ['--all-advisors' => true])
            ->monthlyOn(1, '04:15')
            ->name('fsa-practice-health-snapshots')
            ->withoutOverlapping();

        $schedule->command(RunQuestionnaireOptimisationLayer::class)
            ->cron('30 4 1 1,4,7,10 *')
            ->name('fsa-questionnaire-optimisation-learning-layer')
            ->withoutOverlapping();

        $schedule->command(ProcessScheduledPayments::class)
            ->everyFiveMinutes()
            ->name('fsa-payments-process-scheduled')
            ->withoutOverlapping();

        $schedule->command(ReverifyBrokerFspRegistrations::class)
            ->dailyAt('05:00')
            ->name('fsa-broker-fsp-reverify')
            ->withoutOverlapping();

        $schedule->command(RunCoachSignalCalibrationLayer::class)
            ->monthlyOn(1, '05:30')
            ->name('fsa-coach-signal-calibration-layer')
            ->withoutOverlapping();

        if ((bool) env('FEATURE_ACTIVE_LEARNING', false)) {
            $schedule->command(RunActiveLayerEngine::class)
                ->hourly()
                ->name('fsa-active-learning-layer-engine')
                ->withoutOverlapping();
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
