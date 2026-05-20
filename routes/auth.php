<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Auth\InviteAcceptController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\TermsPendingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('invite/{token}', [InviteAcceptController::class, 'show'])->name('invite.accept');
    Route::post('invite/{token}', [InviteAcceptController::class, 'store'])->name('invite.store');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('mfa/setup', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::get('mfa/challenge', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('mfa/challenge', [MfaChallengeController::class, 'store'])->name('mfa.challenge.store');

    Route::get('terms/pending', TermsPendingController::class)
        ->middleware('mfa')
        ->name('terms.pending');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('mfa')
        ->group(function (): void {
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::get('invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        });
});
