<?php

use App\Enums\Permission;
use App\Http\Controllers\Broker\ReferralStageController;
use App\Http\Controllers\BulkCommunicationOpenController;
use App\Http\Controllers\CalendarController as ActivityCalendarController;
use App\Http\Controllers\Coach\ReferralStageController as CoachReferralStageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PanelAgreementController;
use App\Http\Controllers\PanelApplicationController;
use Illuminate\Support\Facades\Route;

/*
| Public marketing site (no auth) — futureshiftadvisory.nz
| Lives in its own file so routes/controllers/pages stay clearly separated
| from the authenticated portal/advisor/admin areas defined in PLAN.md.
*/
require __DIR__.'/public.php';
require __DIR__.'/auth.php';
require __DIR__.'/advisor.php';
require __DIR__.'/portal.php';

Route::get('communications/open/{token}.gif', BulkCommunicationOpenController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('communications.open');

Route::middleware(['auth', 'verified', 'mfa'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('calendar', ActivityCalendarController::class)->name('calendar.index');
    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('permission:'.Permission::NOTIFICATIONS_VIEW->value)
        ->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->middleware('permission:'.Permission::NOTIFICATIONS_VIEW->value)
        ->name('notifications.mark-all-read');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->middleware('permission:'.Permission::NOTIFICATIONS_VIEW->value)
        ->name('notifications.mark-read');
    Route::patch('broker/referrals/{referral}/stage', ReferralStageController::class)
        ->middleware('permission:'.Permission::BROKER_PORTAL->value)
        ->name('broker.referrals.stage');
    Route::patch('coach/referrals/{referral}/stage', CoachReferralStageController::class)
        ->middleware('permission:'.Permission::COACH_PORTAL->value)
        ->name('coach.referrals.stage');
    Route::post('panel/application', [PanelApplicationController::class, 'store'])
        ->name('panel.application.store');
    Route::patch('panel/application', [PanelApplicationController::class, 'update'])
        ->name('panel.application.update');
    Route::post('panel/agreements/{panelAgreement}/sign', [PanelAgreementController::class, 'sign'])
        ->name('panel.agreements.sign');
    Route::get('panel/agreements/{panelAgreement}/download', [PanelAgreementController::class, 'download'])
        ->name('panel.agreements.download');
});

require __DIR__.'/settings.php';
