<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\InspirationBoardController;
use App\Http\Controllers\Admin\IntegrationCredentialController;
use App\Http\Controllers\Admin\IntegrationHealthController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\LearningUpdateController;
use App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController;
use App\Http\Controllers\Admin\PanelMemberController;
use App\Http\Controllers\Admin\PartnerAgreementController;
use App\Http\Controllers\Admin\PracticeAccountingConnectionController;
use App\Http\Controllers\Admin\PrinciplesRolesController;
use App\Http\Controllers\Admin\ProjectSettingsController;
use App\Http\Controllers\Admin\QuestionnaireController;
use App\Http\Controllers\Admin\RatingFrameworkController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\Admin\ServiceRateController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SurveyController;
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
            Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            Route::patch('staff/{user}', [StaffController::class, 'update'])
                ->middleware('require.fresh-step-up')
                ->name('staff.update');

            Route::get('questionnaires', [QuestionnaireController::class, 'index'])->name('questionnaires.index');
            Route::post('questionnaires', [QuestionnaireController::class, 'store'])->name('questionnaires.store');
            Route::get('questionnaires/{questionnaire}/edit', [QuestionnaireController::class, 'edit'])->name('questionnaires.edit');
            Route::put('questionnaires/{questionnaire}', [QuestionnaireController::class, 'update'])->name('questionnaires.update');
            Route::get('questionnaires/{questionnaire}/preview', [QuestionnaireController::class, 'preview'])->name('questionnaires.preview');
            Route::post('questionnaires/{questionnaire}/publish', [QuestionnaireController::class, 'publish'])->name('questionnaires.publish');

            Route::get('surveys', [SurveyController::class, 'index'])->name('surveys.index');
            Route::post('surveys', [SurveyController::class, 'store'])->name('surveys.store');
            Route::get('surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
            Route::put('surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
            Route::post('surveys/{survey}/publish', [SurveyController::class, 'publish'])->name('surveys.publish');
            Route::post('surveys/{survey}/archive', [SurveyController::class, 'archive'])->name('surveys.archive');
            Route::get('surveys/{survey}/results', [SurveyController::class, 'results'])->name('surveys.results');

            Route::get('terms', [TermsController::class, 'index'])->name('terms.index');
            Route::post('terms', [TermsController::class, 'store'])->name('terms.store');
            Route::get('terms/{termsVersion}/edit', [TermsController::class, 'edit'])->name('terms.edit');
            Route::put('terms/{termsVersion}', [TermsController::class, 'update'])->name('terms.update');
            Route::get('terms/{termsVersion}/preview', [TermsController::class, 'preview'])->name('terms.preview');
            Route::get('terms/{termsVersion}/download', [TermsController::class, 'download'])->name('terms.download');
            Route::post('terms/{termsVersion}/source-file', [TermsController::class, 'uploadSourceFile'])->name('terms.source-file.store');
            Route::get('terms/{termsVersion}/source-file/download', [TermsController::class, 'downloadSourceFile'])->name('terms.source-file.download');
            Route::get('terms/{termsVersion}/publish', [TermsController::class, 'confirmPublish'])->name('terms.publish.create');
            Route::post('terms/{termsVersion}/publish', [TermsController::class, 'publish'])->name('terms.publish');
            Route::post('terms/enforcement/activate', [TermsController::class, 'activateEnforcement'])->name('terms.enforcement.activate');

            Route::get('partner-agreement', [PartnerAgreementController::class, 'index'])->name('partner-agreement.index');
            Route::patch('partner-agreement', [PartnerAgreementController::class, 'update'])
                ->middleware('require.fresh-step-up')
                ->name('partner-agreement.update');
            Route::patch('partner-agreement/reset', [PartnerAgreementController::class, 'reset'])
                ->middleware('require.fresh-step-up')
                ->name('partner-agreement.reset');

            Route::get('principles-roles', [PrinciplesRolesController::class, 'index'])->name('principles-roles.index');
            Route::post('principles-roles', [PrinciplesRolesController::class, 'store'])
                ->middleware('require.fresh-step-up')
                ->name('principles-roles.store');

            Route::get('service-rates', [ServiceRateController::class, 'index'])->name('service-rates.index');
            Route::post('service-rates', [ServiceRateController::class, 'store'])->name('service-rates.store');
            Route::post('service-rates/packages', [ServiceRateController::class, 'storePackage'])->name('service-rates.packages.store');
            Route::patch('service-rates/packages/{serviceRatePackage}', [ServiceRateController::class, 'togglePackage'])->name('service-rates.packages.toggle');

            Route::get('rating-frameworks', [RatingFrameworkController::class, 'index'])->name('rating-frameworks.index');
            Route::post('rating-frameworks/drafts', [RatingFrameworkController::class, 'storeDraft'])->name('rating-frameworks.drafts.store');
            Route::post('rating-frameworks/{ratingFramework}/publish', [RatingFrameworkController::class, 'publish'])->name('rating-frameworks.publish');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::INTEGRATION_HEALTH_VIEW->value])
        ->group(function (): void {
            Route::get('integration-health', [IntegrationHealthController::class, 'index'])
                ->name('integration-health.index');
            Route::post('integration-health/refresh', [IntegrationHealthController::class, 'refresh'])
                ->name('integration-health.refresh');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::AUDIT_VIEW->value])
        ->group(function (): void {
            Route::get('audit-trail', AuditTrailController::class)
                ->middleware('audit.read:audit_trail.viewed')
                ->name('audit-trail.index');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::CREDENTIAL_MANAGE->value])
        ->group(function (): void {
            Route::get('integration-credentials', [IntegrationCredentialController::class, 'index'])
                ->name('integration-credentials.index');
            Route::get('project-settings', [ProjectSettingsController::class, 'index'])
                ->name('project-settings.index');
            Route::patch('project-settings', [ProjectSettingsController::class, 'update'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.update');
            Route::patch('project-settings/reset', [ProjectSettingsController::class, 'reset'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.reset');
            Route::post('project-settings/test-email', [ProjectSettingsController::class, 'testEmail'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.test-email');
            Route::post('project-settings/test-slack', [ProjectSettingsController::class, 'testSlackWebhook'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.test-slack');
            Route::get('project-settings/mail/graph/connect', [MicrosoftGraphMailOAuthController::class, 'connect'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.mail-graph.connect');
            Route::get('project-settings/mail/graph/callback', [MicrosoftGraphMailOAuthController::class, 'callback'])
                ->name('project-settings.mail-graph.callback');
            Route::patch('project-settings/mail/graph/disconnect', [MicrosoftGraphMailOAuthController::class, 'disconnect'])
                ->middleware('require.fresh-step-up')
                ->name('project-settings.mail-graph.disconnect');
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
            Route::get('practice-accounting/{provider}/connect', [PracticeAccountingConnectionController::class, 'connect'])
                ->name('practice-accounting.connect');
            Route::get('practice-accounting/{provider}/callback', [PracticeAccountingConnectionController::class, 'callback'])
                ->name('practice-accounting.callback');
            Route::patch('practice-accounting/{practiceAccountingConnection}/revoke', [PracticeAccountingConnectionController::class, 'revoke'])
                ->middleware('require.fresh-step-up')
                ->name('practice-accounting.revoke');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['mfa', 'permission:'.Permission::REFERENCE_DATA_MANAGE->value])
        ->group(function (): void {
            Route::get('reference-data', [ReferenceDataController::class, 'index'])
                ->name('reference-data.index');
            Route::post('reference-data', [ReferenceDataController::class, 'store'])
                ->name('reference-data.store');
            Route::get('reference-data/evidence/{document}', [ReferenceDataController::class, 'evidence'])
                ->name('reference-data.evidence');
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
