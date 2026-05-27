<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Portal\DashboardController as ClientPortalDashboardController;
use App\Http\Controllers\Portal\EntrepreneurAssessmentController;
use App\Http\Controllers\Portal\EntrepreneurDashboardController;
use App\Http\Controllers\Portal\MessageController;
use App\Http\Controllers\Portal\NpoImpactMetricController;
use App\Http\Controllers\Portal\OnboardingController;
use App\Http\Controllers\Portal\ProposalSignoffController;
use App\Http\Controllers\Portal\WellbeingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::get('/', ClientPortalDashboardController::class)->name('dashboard');
        Route::get('entrepreneur', EntrepreneurDashboardController::class)->name('entrepreneur.dashboard');
        Route::get('entrepreneur/assessments/{planAssessment}', [EntrepreneurAssessmentController::class, 'show'])->name('entrepreneur.assessments.show');
        Route::post('documents', DocumentController::class)->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::post('npo-impact-metrics', NpoImpactMetricController::class)->name('npo-impact-metrics.store');
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('messages/{messageThread}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{messageThread}', [MessageController::class, 'reply'])->name('messages.reply');
        Route::get('proposals/{proposal}/signoff', [ProposalSignoffController::class, 'show'])->name('proposals.signoff.show');
        Route::post('proposals/{proposal}/signoff/{step}', [ProposalSignoffController::class, 'step'])->name('proposals.signoff.step');
        Route::get('wellbeing', [WellbeingController::class, 'show'])->name('wellbeing.show');
        Route::post('wellbeing', [WellbeingController::class, 'store'])->name('wellbeing.store');
        Route::delete('wellbeing/{wellbeingCheckin}', [WellbeingController::class, 'destroy'])->name('wellbeing.destroy');
        Route::get('onboarding', [OnboardingController::class, 'redirect'])->name('onboarding.index');
        Route::get('onboarding/{step}', [OnboardingController::class, 'show'])->name('onboarding.step');
        Route::post('onboarding/{step}', [OnboardingController::class, 'store'])->name('onboarding.store');
    });
