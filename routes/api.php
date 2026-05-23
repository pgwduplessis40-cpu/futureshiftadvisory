<?php

declare(strict_types=1);

use App\Http\Controllers\DdGuestUploadController;
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
