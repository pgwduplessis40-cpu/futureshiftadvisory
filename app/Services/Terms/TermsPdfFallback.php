<?php

declare(strict_types=1);

namespace App\Services\Terms;

use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Pdf\SimpleTextPdf;
use DateTimeInterface;
use Illuminate\Http\Request;

final class TermsPdfFallback
{
    public function __construct(
        private readonly SimpleTextPdf $pdf,
        private readonly TermsDocumentRenderer $documents,
    ) {}

    public function reviewDownload(TermsVersion $version): string
    {
        return $this->pdf->render($version->title, [
            'Future Shift Advisory',
            $this->documentLabel($version).' review copy.',
            'Version '.$version->version.' generated for review on '.now()->toDateTimeString().'.',
            ...$this->documents->plainTextLines($version),
        ]);
    }

    public function userDownload(TermsVersion $version, User $user): string
    {
        return $this->pdf->render($version->title, [
            'Future Shift Advisory',
            $this->documentLabel($version).' download.',
            'Version '.$version->version.' downloaded by '.$user->email.' on '.now()->toDateTimeString().'.',
            ...$this->documents->plainTextLines($version),
        ]);
    }

    /**
     * @param  non-empty-list<TermsVersion>  $versions
     */
    public function signedAcceptance(
        array $versions,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): string {
        $documentLines = collect($versions)
            ->flatMap(fn (TermsVersion $version): array => [
                $this->documentLabel($version).' version: '.$version->version.' - '.$version->title,
                ...$this->documents->plainTextLines($version),
            ])
            ->all();

        return $this->pdf->render('Signed legal document acceptance', [
            'Future Shift Advisory',
            'Signed legal document acceptance record.',
            'Accepted by: '.$user->name.' <'.$user->email.'>',
            'User ID: '.$user->getKey(),
            'Accepted at: '.$acceptedAt->format(DATE_ATOM),
            'IP address: '.($request->ip() ?? ''),
            'User agent: '.((string) $request->userAgent()),
            ...$documentLines,
        ]);
    }

    private function documentLabel(TermsVersion $version): string
    {
        return TermsVersion::documentLabel((string) $version->document_scope);
    }
}
