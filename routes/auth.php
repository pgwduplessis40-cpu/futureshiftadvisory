<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\InspirationBoardController;
use App\Http\Controllers\Admin\IntegrationCredentialController;
use App\Http\Controllers\Admin\IntegrationHealthController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\LearningUpdateController;
use App\Http\Controllers\Admin\PanelMemberController;
use App\Http\Controllers\Admin\QuestionnaireController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\Admin\ServiceRateController;
use App\Http\Controllers\Admin\TermsController;
use App\Http\Controllers\Admin\WelcomeMessageController;
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
        Route::get('terms/download', [TermsPendingController::class, 'download'])->name('terms.download');
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

            Route::get('service-rates', [ServiceRateController::class, 'index'])->name('service-rates.index');
            Route::post('service-rates', [ServiceRateController::class, 'store'])->name('service-rates.store');
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
        ->middleware(['mfa', 'permission:'.Permission::CREDENTIAL_MANAGE->value])
        ->group(function (): void {
            Route::get('integration-credentials', [IntegrationCredentialController::class, 'index'])
                ->name('integration-credentials.index');
            Route::post('integration-credentials', [IntegrationCredentialController::class, 'store'])
                ->middleware('require.fresh-step-up')
                ->name('integration-credentials.store');
            Route::patch('integration-credentials/revoke', [IntegrationCredentialController::class, 'revoke'])
                ->middleware('require.fresh-step-up')
                ->name('integration-credentials.revoke');
            Route::patch('integration-credentials/activate', [IntegrationCredentialController::class, 'activate'])
                ->middleware('require.fresh-step-up')
                ->name('integration-credentials.activate');
            Route::patch('integration-credentials/deactivate', [IntegrationCredentialController::class, 'deactivate'])
                ->middleware('require.fresh-step-up')
                ->name('integration-credentials.deactivate');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::REFERENCE_DATA_MANAGE->value])
        ->group(function (): void {
            Route::get('reference-data', [ReferenceDataController::class, 'index'])
                ->name('reference-data.index');
            Route::post('reference-data', [ReferenceDataController::class, 'store'])
                ->name('reference-data.store');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::WELCOME_MESSAGE_MANAGE->value])
        ->group(function (): void {
            Route::get('welcome-message', [WelcomeMessageController::class, 'index'])
                ->name('welcome-message.index');
            Route::post('welcome-message', [WelcomeMessageController::class, 'store'])
                ->name('welcome-message.store');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::BOARD_MANAGE->value])
        ->group(function (): void {
            Route::get('inspiration-board', [InspirationBoardController::class, 'index'])
                ->name('inspiration-board.index');
            Route::post('inspiration-board', [InspirationBoardController::class, 'store'])
                ->name('inspiration-board.store');
            Route::patch('inspiration-board/{boardPost}', [InspirationBoardController::class, 'update'])
                ->name('inspiration-board.update');
            Route::post('inspiration-board/{boardPost}/publish', [InspirationBoardController::class, 'publish'])
                ->name('inspiration-board.publish');
            Route::post('inspiration-board/{boardPost}/archive', [InspirationBoardController::class, 'archive'])
                ->name('inspiration-board.archive');
            Route::post('inspiration-board/{boardPost}/pin', [InspirationBoardController::class, 'pin'])
                ->name('inspiration-board.pin');
            Route::post('inspiration-board/{boardPost}/unpin', [InspirationBoardController::class, 'unpin'])
                ->name('inspiration-board.unpin');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::LEARNING_UPDATES_VIEW->value])
        ->group(function (): void {
            Route::get('learning-updates', [LearningUpdateController::class, 'index'])
                ->name('learning-updates.index');
            Route::post('learning-updates/rerun', [LearningUpdateController::class, 'rerun'])
                ->middleware('permission:'.Permission::LEARNING_UPDATES_APPROVE->value)
                ->name('learning-updates.rerun');
            Route::patch('learning-updates/{learningUpdate}/decision', [LearningUpdateController::class, 'decide'])
                ->middleware('permission:'.Permission::LEARNING_UPDATES_APPROVE->value)
                ->name('learning-updates.decide');
            Route::patch('learning-update-implementations/{learningUpdateImplementation}/review', [LearningUpdateController::class, 'reviewImpact'])
                ->middleware('permission:'.Permission::LEARNING_UPDATES_APPROVE->value)
                ->name('learning-update-implementations.review');
            Route::patch('learning-update-implementations/{learningUpdateImplementation}/rollback', [LearningUpdateController::class, 'rollback'])
                ->middleware('permission:'.Permission::LEARNING_UPDATES_APPROVE->value)
                ->name('learning-update-implementations.rollback');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::CLIENTS_MANAGE->value])
        ->group(function (): void {
            Route::get('panel-members', [PanelMemberController::class, 'index'])
                ->name('panel-members.index');
            Route::patch('panel-members/{panelMember}/approve', [PanelMemberController::class, 'approve'])
                ->name('panel-members.approve');
            Route::patch('panel-members/{panelMember}/request-info', [PanelMemberController::class, 'requestInfo'])
                ->name('panel-members.request-info');
            Route::patch('panel-members/{panelMember}/decline', [PanelMemberController::class, 'decline'])
                ->name('panel-members.decline');
        });
});
