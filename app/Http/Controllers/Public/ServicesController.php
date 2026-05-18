<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Public\EngagementTypeCatalog;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('public/services', [
            'engagementTypes' => EngagementTypeCatalog::all(),
        ]);
    }
}
