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
            'Terms and conditions review copy.',
            'Version '.$version->version.' generated for review on '.now()->toDateTimeString().'.',
            ...$this->documents->plainTextLines($version),
        ]);
    }

    public function userDownload(TermsVersion $version, User $user): string
    {
        return $this->pdf->render($version->title, [
            'Future Shift Advisory',
            'Terms and conditions download.',
            'Version '.$version->version.' downloaded by '.$user->email.' on '.now()->toDateTimeString().'.',
            ...$this->documents->plainTextLines($version),
        ]);
    }

    public function signedAcceptance(
        TermsVersion $version,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): string {
        return $this->pdf->render('Signed terms acceptance', [
            'Future Shift Advisory',
            'Signed terms and conditions acceptance record.',
            'Accepted by: '.$user->name.' <'.$user->email.'>',
            'User ID: '.$user->getKey(),
            'Terms version: '.$version->version.' - '.$version->title,
            'Accepted at: '.$acceptedAt->format(DATE_ATOM),
            'IP address: '.($request->ip() ?? ''),
            'User agent: '.((string) $request->userAgent()),
            ...$this->documents->plainTextLines($version),
        ]);
    }
}
