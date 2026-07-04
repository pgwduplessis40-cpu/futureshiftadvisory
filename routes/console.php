<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('learning:cadence')->hourly()->withoutOverlapping();
Schedule::command('communications:bulk-send')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('documents:expiry-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('entrepreneurs:recompute-streaks')
    ->dailyAt('00:15')
    ->timezone((string) config('gamification.timezone', 'Pacific/Auckland'))
    ->withoutOverlapping();
Schedule::command('npo:impact-summary-auto-release')->hourly()->withoutOverlapping();
