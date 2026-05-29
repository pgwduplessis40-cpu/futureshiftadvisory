<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ServicesController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing routes
|--------------------------------------------------------------------------
| Anonymous, no auth. Lives under the bare path prefix ("/").
| The authenticated portal/advisor/admin areas live under their own
| route files (portal.php, advisor.php, admin.php) per PLAN.md and
| are loaded separately — they will never collide with these.
*/

// Home keeps the short `home` route name so existing Wayfinder consumers
// (auth layouts) continue to resolve `home()` without churn.
Route::get('/', HomeController::class)->name('home');

Route::name('public.')->group(function (): void {
    Route::get('/services', ServicesController::class)->name('services');
    Route::get('/about', AboutController::class)->name('about');
    Route::get('/faq', FaqController::class)->name('faq');

    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::get('/contact/thanks', [ContactController::class, 'thanks'])->name('contact.thanks');
});

// XML sitemap for search engines and AI answer engines.
Route::get('/sitemap.xml', SitemapController::class)->name('public.sitemap');
