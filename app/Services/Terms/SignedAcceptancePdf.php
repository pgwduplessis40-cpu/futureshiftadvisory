<?php

declare(strict_types=1);

namespace App\Services\Terms;

use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class SignedAcceptancePdf
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly KeyEnvelope $envelope,
        private readonly TermsDocumentRenderer $documents,
        private readonly TermsPdfFallback $fallbackPdf,
    ) {}

    public function create(
        TermsVersion $termsVersion,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): SignedAcceptanceArtifact {
        $termsVersion->loadMissing('clauses');

        $html = $this->documents->signedAcceptanceHtml($termsVersion, $user, $request, $acceptedAt);
        try {
            $pdf = $this->renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->fallbackPdf->signedAcceptance($termsVersion, $user, $request, $acceptedAt);
        }
        $path = $this->path($termsVersion, $user, $acceptedAt);
        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Signed terms acceptance PDF could not be stored.');
        }

        $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));

        return new SignedAcceptanceArtifact(
            path: $path,
            byteSize: strlen($pdf),
            sha256Envelope: $hashEnvelope,
            envelopeMeta: $this->envelope->inspect($hashEnvelope),
        );
    }

    private function path(TermsVersion $termsVersion, User $user, DateTimeInterface $acceptedAt): string
    {
        $version = Str::slug($termsVersion->version) ?: 'version';

        return sprintf(
            'terms/acceptances/%s/%s/%s-terms-%s.pdf',
            $user->getKey(),
            $acceptedAt->format('Y/m'),
            Str::uuid(),
            $version,
        );
    }
}
