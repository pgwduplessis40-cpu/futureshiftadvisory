<?php

declare(strict_types=1);

use App\Http\Controllers\Webhook\ProspectIntakeController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/prospects', [ProspectIntakeController::class, 'store'])
    ->name('webhooks.prospects.store');
