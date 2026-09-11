<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\EntrepreneurServiceController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\IdeaValidationPurchaseController;
use App\Http\Controllers\Public\LlmsTxtController;
use App\Http\Controllers\Public\ServicesController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TermsAndPrivacyController;
use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
Route::get('/validate-idea', function (EntrepreneurServiceOffer $offers) {
    return Inertia::render('public/validate-idea', [
        'offer' => $offers->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION),
    ]);
})->name('public.validate-idea');
Route::get('/validate-idea/purchase', [IdeaValidationPurchaseController::class, 'show'])
    ->name('public.validate-idea.purchase');
Route::post('/validate-idea/purchase', [IdeaValidationPurchaseController::class, 'register'])
    ->middleware('guest')
    ->name('public.validate-idea.purchase.register');
Route::get('/validate-idea/purchase/{purchase}/verify/{hash}', [IdeaValidationPurchaseController::class, 'verify'])
    ->middleware('signed')
    ->whereUuid('purchase')
    ->name('public.validate-idea.purchase.verify');
Route::middleware(['auth', 'throttle:6,1'])->group(function (): void {
    Route::post('/validate-idea/purchase/resend-verification', [IdeaValidationPurchaseController::class, 'resendVerification'])
        ->name('public.validate-idea.purchase.resend-verification');
    Route::post('/validate-idea/purchase/payment-intent', [IdeaValidationPurchaseController::class, 'paymentIntent'])
        ->name('public.validate-idea.purchase.payment-intent');
    Route::post('/validate-idea/purchase/confirm-payment', [IdeaValidationPurchaseController::class, 'confirmPayment'])
        ->name('public.validate-idea.purchase.confirm-payment');
    Route::post('/validate-idea/purchase/confirm-fixture-payment', [IdeaValidationPurchaseController::class, 'confirmFixturePayment'])
        ->name('public.validate-idea.purchase.confirm-fixture-payment');
});
Route::get('/terms-and-privacy', [TermsAndPrivacyController::class, 'show'])
    ->name('public.terms-and-privacy');
Route::get('/terms-and-privacy.json', [TermsAndPrivacyController::class, 'json'])
    ->name('public.terms-and-privacy.json');

Route::name('public.')->group(function (): void {
    Route::get('/services', ServicesController::class)->name('services');
    Route::get('/services/entrepreneur', EntrepreneurServiceController::class)
        ->name('services.entrepreneur');
    Route::get('/about', AboutController::class)->name('about');
    Route::get('/faq', FaqController::class)->name('faq');

    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:public-contact')
        ->name('contact.store');
    Route::get('/contact/thanks', [ContactController::class, 'thanks'])->name('contact.thanks');
});

// XML sitemap for search engines and AI answer engines.
Route::get('/sitemap.xml', SitemapController::class)->name('public.sitemap');

// Plain-markdown practice summary for AI answer engines (llmstxt.org).
Route::get('/llms.txt', LlmsTxtController::class)->name('public.llms');
