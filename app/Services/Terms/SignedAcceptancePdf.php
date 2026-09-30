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

    /**
     * @param  non-empty-list<TermsVersion>  $versions
     */
    public function create(
        array $versions,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): SignedAcceptanceArtifact {
        foreach ($versions as $version) {
            $version->loadMissing('clauses');
        }

        $html = $this->documents->signedAcceptanceHtml($versions, $user, $request, $acceptedAt);
        try {
            $pdf = $this->renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->fallbackPdf->signedAcceptance($versions, $user, $request, $acceptedAt);
        }
        $path = $this->path($versions, $user, $acceptedAt);
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

    /**
     * @param  non-empty-list<TermsVersion>  $versions
     */
    private function path(array $versions, User $user, DateTimeInterface $acceptedAt): string
    {
        $versionsLabel = collect($versions)
            ->map(fn (TermsVersion $version): string => Str::slug($version->version) ?: 'version')
            ->implode('-');

        return sprintf(
            'terms/acceptances/%s/%s/%s-legal-documents-%s.pdf',
            $user->getKey(),
            $acceptedAt->format('Y/m'),
            Str::uuid(),
            $versionsLabel,
        );
    }
}
