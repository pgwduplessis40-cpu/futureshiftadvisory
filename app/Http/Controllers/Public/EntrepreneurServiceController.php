<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dedicated landing page for the Entrepreneur Module.
 *
 * The /services page introduces all five engagement types in brief; this page
 * gives the entrepreneur lane room to answer the questions founders actually
 * search for - idea validation, business plans, and funding readiness - each
 * under its own heading.
 */
class EntrepreneurServiceController extends Controller
{
    public function __invoke(EntrepreneurServiceOffer $offers): Response
    {
        return Inertia::render('public/services/entrepreneur', [
            'ideaValidationOffer' => $offers->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION),
        ]);
    }
}
