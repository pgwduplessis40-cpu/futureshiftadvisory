<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Panels\PanelAccessException;
use App\Services\Panels\PanelAgreementPdfRenderer;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class PanelAgreementController extends Controller
{
    public function sign(Request $request, PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $panelOnboarding->signAgreement($panelAgreement, $user);
        } catch (PanelAccessException|InvalidArgumentException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['agreement' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            $message = 'We could not sign the agreement right now. Please try again or contact support.';

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $message,
            ]);

            return back()->withErrors(['agreement' => $message]);
        }

        return to_route('dashboard')->with('status', 'panel-agreement-signed');
    }

    public function download(Request $request, PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): StreamedResponse
    {
        $this->authorizeAgreementAccess($request, $panelAgreement);
        $panelAgreement = $this->ensureCurrentSignedPdf($panelAgreement, $panelOnboarding);

        abort_unless(filled($panelAgreement->pdf_path), 404);
        abort_unless(Storage::disk('secure_local')->exists($panelAgreement->pdf_path), 404);

        return Storage::disk('secure_local')->download(
            $panelAgreement->pdf_path,
            'future-shift-panel-agreement.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function view(Request $request, PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): StreamedResponse
    {
        $this->authorizeAgreementAccess($request, $panelAgreement);
        $panelAgreement = $this->ensureCurrentSignedPdf($panelAgreement, $panelOnboarding);

        abort_unless(filled($panelAgreement->pdf_path), 404);
        abort_unless(Storage::disk('secure_local')->exists($panelAgreement->pdf_path), 404);

        return Storage::disk('secure_local')->response(
            $panelAgreement->pdf_path,
            'future-shift-panel-agreement.pdf',
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    private function authorizeAgreementAccess(Request $request, PanelAgreement $panelAgreement): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $panelAgreement->loadMissing('panelMember');
        $member = $panelAgreement->panelMember;

        abort_unless($member instanceof PanelMember, 404);
        abort_unless(
            (string) $member->user_id === (string) $user->getKey()
                || $user->can(Permission::CLIENTS_MANAGE->value),
            403,
        );
    }

    private function ensureCurrentSignedPdf(PanelAgreement $panelAgreement, PanelOnboarding $panelOnboarding): PanelAgreement
    {
        if ($panelAgreement->status !== PanelAgreement::STATUS_SIGNED) {
            return $panelAgreement;
        }

        if (! filled($panelAgreement->pdf_path) || ! Storage::disk('secure_local')->exists($panelAgreement->pdf_path)) {
            return $panelOnboarding->refreshSignedAgreementPdf($panelAgreement);
        }

        if (! $this->storedPdfLooksLegacy($panelAgreement->pdf_path)) {
            return $panelAgreement;
        }

        try {
            return $panelOnboarding->refreshSignedAgreementPdf($panelAgreement);
        } catch (Throwable $exception) {
            report($exception);

            return $panelAgreement->refresh();
        }
    }

    private function storedPdfLooksLegacy(string $path): bool
    {
        try {
            $content = Storage::disk('secure_local')->get($path);
        } catch (Throwable) {
            return false;
        }

        if (Str::contains($content, PanelAgreementPdfRenderer::BRANDED_FALLBACK_MARKER)) {
            return false;
        }

        if (Str::contains($content, 'FSA-PANEL-AGREEMENT-BRANDED-FALLBACK')) {
            return true;
        }

        if (Str::contains($content, [
            'broker_clauses',
            'coach_clauses',
            'panel_type:',
            'client_consent_required:',
            'reverse_referrals_no_auto_access:',
        ])) {
            return true;
        }

        $looksLikePlainFallbackPdf = Str::contains($content, "BT\n/F1 16 Tf 50 792 Td")
            && Str::contains($content, '/BaseFont /Helvetica >>');

        return $looksLikePlainFallbackPdf && Str::contains($content, [
            'Future Shift Advisory Partner Agreement',
            'Future Shift Advisory partner agreement.',
            'Agreement summary:',
            'Broker operating terms:',
            'Coach operating terms:',
        ]);
    }
}
