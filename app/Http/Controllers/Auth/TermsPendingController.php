<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class TermsPendingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('auth/terms-pending');
    }
}
