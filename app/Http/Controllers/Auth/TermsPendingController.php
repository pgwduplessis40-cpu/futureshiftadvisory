<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\TermsDeclinedUrgentNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Terms\SignedAcceptancePdf;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Terms\TermsDocumentRenderer;
use App\Services\Terms\TermsPdfFallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TermsPendingController extends Controller
{
    public function __construct(
        private readonly TermsAcceptanceGate $gate,
        private readonly SignedAcceptancePdf $signedPdf,
        private readonly AuditWriter $auditWriter,
        private readonly TermsDocumentRenderer $documents,
        private readonly TermsPdfFallback $fallbackPdf,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $version = $this->gate->latestPublishedVersion(withClauses: true);

        if (! $version instanceof TermsVersion) {
            return redirect()->intended(route('dashboard'));
        }

        if (! $this->gate->requiresAcceptance($user) && ! $this->gate->hasDeclinedTermsSuspension($user)) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('terms/Gate', [
            'version' => $this->versionPayload($version),
            'acceptUrl' => route('terms.accept'),
            'declineUrl' => route('terms.decline'),
            'downloadUrl' => route('terms.download'),
            'hasDeclined' => $this->gate->hasDeclinedTermsSuspension($user),
        ]);
    }

    public function download(Request $request, PdfRenderer $renderer): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
        $version = $this->gate->latestPublishedVersion(withClauses: true);
        abort_unless($version instanceof TermsVersion, 404);

        $html = $this->documents->userDownloadHtml($version, $user);
        try {
            $pdf = $renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->fallbackPdf->userDownload($version, $user);
        }
        $filename = Str::slug('future-shift-advisory-terms-'.$version->version).'.pdf';

        $this->auditWriter->record('terms.downloaded', subject: $version, actor: $user, after: [
            'version' => $version->version,
            'byte_size' => strlen($pdf),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate([
            'scroll_end_confirmed' => ['accepted'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $version = $this->gate->latestPublishedVersion(withClauses: true);
        abort_unless($version instanceof TermsVersion, 404);

        if (! $this->gate->requiresAcceptance($user) && ! $this->gate->hasDeclinedTermsSuspension($user)) {
            return redirect()->intended(route('dashboard'));
        }

        $acceptedAt = now();
        $artifact = $this->signedPdf->create($version, $user, $request, $acceptedAt);

        DB::transaction(function () use ($artifact, $acceptedAt, $request, $user, $version): void {
            $acceptance = TermsAcceptance::query()->create([
                'user_id' => $user->getKey(),
                'terms_version_id' => $version->getKey(),
                'accepted_at' => $acceptedAt,
                'signed_pdf_path' => $artifact->path,
                'signed_pdf_sha256_envelope' => $artifact->sha256Envelope,
                'signed_pdf_envelope_meta' => $artifact->envelopeMeta,
                'signed_pdf_byte_size' => $artifact->byteSize,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($this->gate->hasDeclinedTermsSuspension($user)) {
                $user->forceFill([
                    'suspended_at' => null,
                    'suspended_reason' => null,
                ])->save();
            }

            $this->auditWriter->record('terms.accepted', subject: $acceptance, actor: $user, after: [
                'terms_version_id' => $version->getKey(),
                'version' => $version->version,
                'signed_pdf_path' => $artifact->path,
                'signed_pdf_byte_size' => $artifact->byteSize,
                'signed_pdf_envelope_meta' => $artifact->envelopeMeta,
            ]);
        });

        return redirect()->intended(route('dashboard'))->with('status', 'terms-accepted');
    }

    public function decline(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $version = $this->gate->latestPublishedVersion(withClauses: true);
        abort_unless($version instanceof TermsVersion, 404);

        if (! $this->gate->requiresAcceptance($user) && ! $this->gate->hasDeclinedTermsSuspension($user)) {
            return redirect()->intended(route('dashboard'));
        }

        $declinedAt = now();
        $acceptance = DB::transaction(function () use ($declinedAt, $request, $user, $version): TermsAcceptance {
            $acceptance = TermsAcceptance::query()->create([
                'user_id' => $user->getKey(),
                'terms_version_id' => $version->getKey(),
                'declined_at' => $declinedAt,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $user->forceFill([
                'suspended_at' => $declinedAt,
                'suspended_reason' => 'terms_declined',
            ])->save();

            $this->auditWriter->record('terms.declined', subject: $acceptance, actor: $user, after: [
                'terms_version_id' => $version->getKey(),
                'version' => $version->version,
                'suspended_reason' => 'terms_declined',
            ]);

            return $acceptance;
        });

        Notification::send($this->urgentRecipients(), new TermsDeclinedUrgentNotification(
            declinedUser: $user,
            termsVersion: $version,
            acceptance: $acceptance,
        ));

        return to_route('terms.declined');
    }

    public function declined(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('terms/Declined', [
            'reviewUrl' => route('terms.pending'),
            'isSuspended' => $this->gate->hasDeclinedTermsSuspension($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(TermsVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'title' => $version->title,
            'published_at' => $version->published_at?->toIso8601String(),
            'source_preview_html' => $this->documents->sourcePreviewHtml($version),
            'clauses' => $version->clauses->map(fn ($clause): array => [
                'id' => $clause->id,
                'clause_number' => $clause->clause_number,
                'title' => $clause->title,
                'body' => $clause->body,
                'material' => $clause->material,
            ])->values(),
        ];
    }

    /**
     * @return iterable<int, User>
     */
    private function urgentRecipients(): iterable
    {
        return User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->get();
    }
}
