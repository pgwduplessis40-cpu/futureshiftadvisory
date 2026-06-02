<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarController as ActivityCalendarController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Portal\DashboardController as ClientPortalDashboardController;
use App\Http\Controllers\Portal\DdBusinessPlanController;
use App\Http\Controllers\Portal\EntrepreneurAssessmentController;
use App\Http\Controllers\Portal\EntrepreneurDashboardController;
use App\Http\Controllers\Portal\EntrepreneurPlanController;
use App\Http\Controllers\Portal\InspirationBoardController;
use App\Http\Controllers\Portal\MessageController;
use App\Http\Controllers\Portal\NpoImpactMetricController;
use App\Http\Controllers\Portal\OnboardingController;
use App\Http\Controllers\Portal\ProposalSignoffController;
use App\Http\Controllers\Portal\ReportController;
use App\Http\Controllers\Portal\WellbeingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::get('/', ClientPortalDashboardController::class)->name('dashboard');
        Route::get('calendar', ActivityCalendarController::class)->name('calendar.index');
        Route::get('acquisition-plan', [DdBusinessPlanController::class, 'show'])->name('dd-plan.show');
        Route::get('acquisition-plan/preview', [DdBusinessPlanController::class, 'preview'])->name('dd-plan.preview');
        Route::post('acquisition-plan', [DdBusinessPlanController::class, 'store'])->name('dd-plan.store');
        Route::post('acquisition-plan/sections', [DdBusinessPlanController::class, 'section'])->name('dd-plan.sections.store');
        Route::post('acquisition-plan/sections/{planSection}/guidance', [DdBusinessPlanController::class, 'guidance'])->name('dd-plan.sections.guidance');
        Route::post('acquisition-plan/complete', [DdBusinessPlanController::class, 'complete'])->name('dd-plan.complete');
        Route::post('acquisition-plan/business-advice', [DdBusinessPlanController::class, 'requestAdvice'])->name('dd-plan.business-advice.store');
        Route::get('entrepreneur', EntrepreneurDashboardController::class)->name('entrepreneur.dashboard');
        Route::get('entrepreneur/plan', [EntrepreneurPlanController::class, 'show'])->name('entrepreneur.plan.show');
        Route::get('entrepreneur/plan/preview', [EntrepreneurPlanController::class, 'preview'])->name('entrepreneur.plan.preview');
        Route::post('entrepreneur/readiness', [EntrepreneurPlanController::class, 'readiness'])->name('entrepreneur.readiness.store');
        Route::post('entrepreneur/idea-validation', [EntrepreneurPlanController::class, 'ideaValidation'])->name('entrepreneur.idea-validation.store');
        Route::post('entrepreneur/plan/start', [EntrepreneurPlanController::class, 'start'])->name('entrepreneur.plan.start');
        Route::post('entrepreneur/plan/sections', [EntrepreneurPlanController::class, 'section'])->name('entrepreneur.plan.sections.store');
        Route::post('entrepreneur/plan/sections/{planSection}/guidance', [EntrepreneurPlanController::class, 'guidance'])->name('entrepreneur.plan.sections.guidance');
        Route::post('entrepreneur/plan/submit', [EntrepreneurPlanController::class, 'submit'])->name('entrepreneur.plan.submit');
        Route::post('entrepreneur/advisory-request', [EntrepreneurPlanController::class, 'requestAdvisory'])->name('entrepreneur.advisory-request.store');
        Route::get('entrepreneur/assessments/{planAssessment}', [EntrepreneurAssessmentController::class, 'show'])->name('entrepreneur.assessments.show');
        Route::post('documents', DocumentController::class)->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('npo-impact-metrics', NpoImpactMetricController::class)->name('npo-impact-metrics.store');
        Route::get('inspiration-board', [InspirationBoardController::class, 'index'])->name('inspiration-board.index');
        Route::get('inspiration-board/{boardPost}/image', [InspirationBoardController::class, 'image'])->name('inspiration-board.image');
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
