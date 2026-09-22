<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use App\Support\Public\EngagementTypeCatalog;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(EntrepreneurServiceOffer $offers): Response
    {
        return Inertia::render('public/home', [
            'engagementTypes' => EngagementTypeCatalog::summaries(),
            'ideaValidationOffer' => $offers->forScope(
                ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            ),
        ]);
    }
}
