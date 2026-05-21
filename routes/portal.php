<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Portal\DashboardController as ClientPortalDashboardController;
use App\Http\Controllers\Portal\EntrepreneurDashboardController;
use App\Http\Controllers\Portal\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::get('/', ClientPortalDashboardController::class)->name('dashboard');
        Route::get('entrepreneur', EntrepreneurDashboardController::class)->name('entrepreneur.dashboard');
        Route::post('documents', DocumentController::class)->name('documents.store');
        Route::get('onboarding', [OnboardingController::class, 'redirect'])->name('onboarding.index');
        Route::get('onboarding/{step}', [OnboardingController::class, 'show'])->name('onboarding.step');
        Route::post('onboarding/{step}', [OnboardingController::class, 'store'])->name('onboarding.store');
    });
