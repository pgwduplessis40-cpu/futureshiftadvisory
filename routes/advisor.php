<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Advisor\ClientController;
use App\Http\Controllers\Advisor\DocumentVerificationController;
use App\Http\Controllers\Advisor\EntrepreneurController;
use App\Http\Controllers\Advisor\OffboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])
    ->prefix('advisor')
    ->name('advisor.')
    ->group(function (): void {
        Route::get('clients', [ClientController::class, 'index'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.create');
        Route::post('clients/lookup-nzbn', [ClientController::class, 'lookupNzbn'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.lookup-nzbn');
        Route::post('clients', [ClientController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.store');
        Route::get('clients/{client}/offboarding', [OffboardingController::class, 'create'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.offboarding.create');
        Route::post('clients/{client}/offboarding', [OffboardingController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.offboarding.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.show');

        Route::get('entrepreneurs', [EntrepreneurController::class, 'index'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.index');
        Route::get('entrepreneurs/create', [EntrepreneurController::class, 'create'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.create');
        Route::post('entrepreneurs', [EntrepreneurController::class, 'store'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.store');
        Route::get('entrepreneurs/{entrepreneurProfile}', [EntrepreneurController::class, 'show'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.show');

        Route::patch('document-verifications/{documentVerification}', [DocumentVerificationController::class, 'update'])
            ->middleware('permission:'.Permission::DOCUMENTS_VERIFY->value)
            ->name('document-verifications.update');
    });
