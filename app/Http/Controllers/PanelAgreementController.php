<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PanelAgreementController extends Controller
{
    public function sign(Request $request, PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $panelOnboarding->signAgreement($panelAgreement, $user);

        return to_route('dashboard')->with('status', 'panel-agreement-signed');
    }

    public function download(Request $request, PanelAgreement $panelAgreement): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $panelAgreement->loadMissing('panelMember');
        $member = $panelAgreement->panelMember;

        abort_unless($member instanceof PanelMember, 404);
        abort_unless((string) $member->user_id === (string) $user->getKey(), 403);
        abort_unless(filled($panelAgreement->pdf_path), 404);
        abort_unless(Storage::disk('secure_local')->exists($panelAgreement->pdf_path), 404);

        return Storage::disk('secure_local')->download(
            $panelAgreement->pdf_path,
            'future-shift-panel-agreement.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
