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
use App\Http\Controllers\CoBrowse\CoBrowseConnectionController;
use App\Http\Controllers\CoBrowse\CoBrowseSessionController;
use App\Http\Controllers\ScreenShare\ScreenShareConnectionController;
use App\Http\Controllers\ScreenShare\ScreenShareSessionController;
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
    Route::post('co-browse/connections/{connection}/pending-prompt', [CoBrowseConnectionController::class, 'pendingPrompt'])
        ->whereUuid('connection')
        ->name('co-browse.connections.pending-prompt');
    Route::post('co-browse/connections/{connection}/heartbeat', [CoBrowseConnectionController::class, 'heartbeat'])
        ->whereUuid('connection')
        ->name('co-browse.connections.heartbeat');
    Route::post('co-browse/sessions/{session}/actions', [CoBrowseSessionController::class, 'action'])
        ->whereUuid('session')
        ->name('co-browse.sessions.actions.store');
    Route::post('co-browse/sessions/{session}/pending-actions', [CoBrowseSessionController::class, 'pendingActions'])
        ->whereUuid('session')
        ->name('co-browse.sessions.pending-actions');
    Route::post('co-browse/sessions/{session}/status', [CoBrowseSessionController::class, 'status'])
        ->whereUuid('session')
        ->name('co-browse.sessions.status');
    Route::post('co-browse/sessions/{session}/heartbeat', [CoBrowseSessionController::class, 'heartbeat'])
        ->whereUuid('session')
        ->name('co-browse.sessions.heartbeat');
    Route::post('co-browse/sessions/{session}/end', [CoBrowseSessionController::class, 'end'])
        ->whereUuid('session')
        ->name('co-browse.sessions.end');
    Route::post('screen-share/connections/{connection}/pending-prompt', [ScreenShareConnectionController::class, 'pendingPrompt'])
        ->whereUuid('connection')
        ->name('screen-share.connections.pending-prompt');
    Route::post('screen-share/connections/{connection}/heartbeat', [ScreenShareConnectionController::class, 'heartbeat'])
        ->whereUuid('connection')
        ->name('screen-share.connections.heartbeat');
    Route::post('screen-share/sessions/{session}/active', [ScreenShareSessionController::class, 'active'])
        ->whereUuid('session')
        ->name('screen-share.sessions.active');
    Route::post('screen-share/sessions/{session}/signal', [ScreenShareSessionController::class, 'signal'])
        ->whereUuid('session')
        ->name('screen-share.sessions.signal');
    Route::post('screen-share/sessions/{session}/pending-signals', [ScreenShareSessionController::class, 'pendingSignals'])
        ->whereUuid('session')
        ->name('screen-share.sessions.pending-signals');
    Route::post('screen-share/sessions/{session}/ice-servers', [ScreenShareSessionController::class, 'iceServers'])
        ->whereUuid('session')
        ->name('screen-share.sessions.ice-servers');
    Route::post('screen-share/sessions/{session}/heartbeat', [ScreenShareSessionController::class, 'heartbeat'])
        ->whereUuid('session')
        ->name('screen-share.sessions.heartbeat');
    Route::post('screen-share/sessions/{session}/end', [ScreenShareSessionController::class, 'end'])
        ->whereUuid('session')
        ->name('screen-share.sessions.end');
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
    Route::get('panel/agreements/{panelAgreement}/view', [PanelAgreementController::class, 'view'])
        ->name('panel.agreements.view');
    Route::get('panel/agreements/{panelAgreement}/download', [PanelAgreementController::class, 'download'])
        ->name('panel.agreements.download');
});

require __DIR__.'/settings.php';
