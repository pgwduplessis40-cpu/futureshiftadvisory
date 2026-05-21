<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\TermsDeclinedUrgentNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Terms\SignedAcceptancePdf;
use App\Services\Terms\TermsAcceptanceGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

final class TermsPendingController extends Controller
{
    public function __construct(
        private readonly TermsAcceptanceGate $gate,
        private readonly SignedAcceptancePdf $signedPdf,
        private readonly AuditWriter $auditWriter,
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
            'hasDeclined' => $this->gate->hasDeclinedTermsSuspension($user),
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
