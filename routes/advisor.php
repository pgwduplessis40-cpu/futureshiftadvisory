<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Advisor\ClientController;
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
        Route::get('clients/{client}', [ClientController::class, 'show'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.show');
    });
