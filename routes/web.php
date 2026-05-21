<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Public marketing site (no auth) — futureshiftadvisory.nz
| Lives in its own file so routes/controllers/pages stay clearly separated
| from the authenticated portal/advisor/admin areas defined in PLAN.md.
*/
require __DIR__.'/public.php';
require __DIR__.'/auth.php';
require __DIR__.'/advisor.php';
require __DIR__.'/portal.php';

Route::middleware(['auth', 'verified', 'mfa'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
