<?php

use App\Http\Controllers\Settings\CalendarController;
use App\Http\Controllers\Settings\CommunicationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/deactivation-request', [ProfileController::class, 'requestDeactivation'])->name('profile.deactivation-request');
    Route::get('settings/communication', [CommunicationController::class, 'edit'])->name('communication.edit');
    Route::put('settings/communication', [CommunicationController::class, 'update'])->name('communication.update');
    Route::get('settings/calendar', [CalendarController::class, 'edit'])->name('calendar.edit');
    Route::get('settings/calendar/{provider}/connect', [CalendarController::class, 'connect'])->name('calendar.connect');
    Route::get('settings/calendar/{provider}/callback', [CalendarController::class, 'callback'])->name('calendar.callback');
    Route::post('settings/calendar/{calendarConnection}/sync', [CalendarController::class, 'sync'])
        ->whereUuid('calendarConnection')
        ->name('calendar.sync');
    Route::patch('settings/calendar/{calendarConnection}/revoke', [CalendarController::class, 'revoke'])
        ->whereUuid('calendarConnection')
        ->name('calendar.revoke');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
});

Route::middleware(['auth', 'verified', 'mfa'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
