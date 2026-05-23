<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\IntegrationHealthController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\LearningUpdateController;
use App\Http\Controllers\Admin\QuestionnaireController;
use App\Http\Controllers\Admin\TermsController;
use App\Http\Controllers\Auth\InviteAcceptController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\TermsPendingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('invite/{token}', [InviteAcceptController::class, 'show'])->name('invite.accept');
    Route::post('invite/{token}', [InviteAcceptController::class, 'store'])->name('invite.store');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('mfa/setup', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::get('mfa/challenge', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('mfa/challenge', [MfaChallengeController::class, 'store'])->name('mfa.challenge.store');

    Route::middleware('mfa')->group(function (): void {
        Route::get('terms', [TermsPendingController::class, 'show'])->name('terms.show');
        Route::get('terms/pending', [TermsPendingController::class, 'show'])->name('terms.pending');
        Route::post('terms/accept', [TermsPendingController::class, 'accept'])->name('terms.accept');
        Route::post('terms/decline', [TermsPendingController::class, 'decline'])->name('terms.decline');
        Route::get('terms/declined', [TermsPendingController::class, 'declined'])->name('terms.declined');
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'role:'.User::TYPE_SUPER_ADMIN])
        ->group(function (): void {
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::get('invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');

            Route::get('questionnaires', [QuestionnaireController::class, 'index'])->name('questionnaires.index');
            Route::post('questionnaires', [QuestionnaireController::class, 'store'])->name('questionnaires.store');
            Route::get('questionnaires/{questionnaire}/edit', [QuestionnaireController::class, 'edit'])->name('questionnaires.edit');
            Route::put('questionnaires/{questionnaire}', [QuestionnaireController::class, 'update'])->name('questionnaires.update');
            Route::get('questionnaires/{questionnaire}/preview', [QuestionnaireController::class, 'preview'])->name('questionnaires.preview');
            Route::post('questionnaires/{questionnaire}/publish', [QuestionnaireController::class, 'publish'])->name('questionnaires.publish');

            Route::get('terms', [TermsController::class, 'index'])->name('terms.index');
            Route::post('terms', [TermsController::class, 'store'])->name('terms.store');
            Route::get('terms/{termsVersion}/edit', [TermsController::class, 'edit'])->name('terms.edit');
            Route::put('terms/{termsVersion}', [TermsController::class, 'update'])->name('terms.update');
            Route::get('terms/{termsVersion}/preview', [TermsController::class, 'preview'])->name('terms.preview');
            Route::get('terms/{termsVersion}/publish', [TermsController::class, 'confirmPublish'])->name('terms.publish.create');
            Route::post('terms/{termsVersion}/publish', [TermsController::class, 'publish'])->name('terms.publish');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::INTEGRATION_HEALTH_VIEW->value])
        ->group(function (): void {
            Route::get('integration-health', IntegrationHealthController::class)
                ->name('integration-health.index');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::LEARNING_UPDATES_VIEW->value])
        ->group(function (): void {
            Route::get('learning-updates', [LearningUpdateController::class, 'index'])
                ->name('learning-updates.index');
            Route::patch('learning-updates/{learningUpdate}/decision', [LearningUpdateController::class, 'decide'])
                ->middleware('permission:'.Permission::LEARNING_UPDATES_APPROVE->value)
                ->name('learning-updates.decide');
        });
});
