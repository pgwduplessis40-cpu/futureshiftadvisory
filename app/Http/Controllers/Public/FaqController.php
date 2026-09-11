<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Public\FaqCatalog;
use App\Support\Public\IdeaValidationOffer;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function __invoke(): Response
    {
        $offer = IdeaValidationOffer::current();

        return Inertia::render('public/faq', [
            'faqs' => FaqCatalog::all($offer['ex_gst_formatted'] ?? null),
        ]);
    }
}
