<?php

declare(strict_types=1);

use App\Http\Controllers\AdvisorApi\ClientController as AdvisorApiClientController;
use App\Http\Controllers\AdvisorApi\WriteController as AdvisorApiWriteController;
use App\Http\Controllers\DdGuestUploadController;
use App\Http\Controllers\MobileApi\ClientController as MobileApiClientController;
use App\Http\Controllers\MobileApi\MeController as MobileApiMeController;
use App\Http\Controllers\MobileApi\VoiceSessionController as MobileApiVoiceSessionController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Http\Controllers\Webhook\ProspectIntakeController;
use Illuminate\Support\Facades\Route;

Route::post('dd/guest-uploads/{token}', DdGuestUploadController::class)
    ->name('dd.guest-uploads.store');

Route::post('webhooks/prospects', [ProspectIntakeController::class, 'store'])
    ->name('webhooks.prospects.store');

Route::post('webhooks/payments/stripe', [PaymentWebhookController::class, 'stripe'])
    ->name('webhooks.payments.stripe');

Route::post('webhooks/payments/windcave', [PaymentWebhookController::class, 'windcave'])
    ->name('webhooks.payments.windcave');

Route::prefix('advisor/v1')
    ->as('advisor-api.')
    ->middleware(['advisor.api', 'throttle:advisor-api'])
    ->group(function (): void {
        Route::get('clients', [AdvisorApiClientController::class, 'index'])
            ->name('clients.index');
        Route::get('clients/{client}', [AdvisorApiClientController::class, 'show'])
            ->name('clients.show');
        Route::post('clients/{client}/meeting-notes', [AdvisorApiWriteController::class, 'meetingNote'])
            ->name('clients.meeting-notes.store');
        Route::post('clients/{client}/actions', [AdvisorApiWriteController::class, 'action'])
            ->name('clients.actions.store');
    });

Route::prefix('mobile/v1')
    ->as('mobile-api.')
    ->middleware(['mobile.api', 'throttle:mobile-api'])
    ->group(function (): void {
        Route::get('me', [MobileApiMeController::class, 'index'])
            ->name('me');
        Route::get('clients', [MobileApiClientController::class, 'index'])
            ->name('clients.index');
        Route::get('clients/{client}', [MobileApiClientController::class, 'show'])
            ->name('clients.show');
        Route::post('voice-assistant/sessions', [MobileApiVoiceSessionController::class, 'store'])
            ->name('voice-assistant.sessions.store');
    });
