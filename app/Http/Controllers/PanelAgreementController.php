<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PanelAgreement;
use App\Models\User;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PanelAgreementController extends Controller
{
    public function sign(Request $request, PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $panelOnboarding->signAgreement($panelAgreement, $user);

        return to_route('dashboard')->with('status', 'panel-agreement-signed');
    }
}
