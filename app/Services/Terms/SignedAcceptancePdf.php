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

final class SignedAcceptancePdf
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly KeyEnvelope $envelope,
    ) {}

    public function create(
        TermsVersion $termsVersion,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): SignedAcceptanceArtifact {
        $termsVersion->loadMissing('clauses');

        $pdf = $this->renderer->render($this->html($termsVersion, $user, $request, $acceptedAt));
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

    private function html(
        TermsVersion $termsVersion,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): string {
        $clauses = $termsVersion->clauses
            ->map(fn ($clause): string => sprintf(
                '<section class="clause"><h2>Clause %d: %s</h2><div class="body">%s</div></section>',
                $clause->clause_number,
                $this->escape($clause->title),
                $this->escape($clause->body),
            ))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Signed terms acceptance</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.brand p, .meta p { margin: 0; }
.meta { background: #f4f7f5; border: 1px solid #d8e2dc; margin-bottom: 22px; padding: 12px; }
.meta strong { display: inline-block; min-width: 128px; }
.clause { break-inside: avoid; margin: 0 0 16px; }
.clause h2 { color: #214f44; font-size: 15px; margin: 0 0 6px; }
.body { white-space: pre-wrap; }
</style>
</head>
<body>
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>Signed terms and conditions acceptance record</p>
</header>
<section class="meta">
<p><strong>Accepted by</strong> %s &lt;%s&gt;</p>
<p><strong>User ID</strong> %s</p>
<p><strong>Terms version</strong> %s - %s</p>
<p><strong>Accepted at</strong> %s</p>
<p><strong>IP address</strong> %s</p>
<p><strong>User agent</strong> %s</p>
</section>
%s
</body>
</html>
HTML,
            $this->escape($user->name),
            $this->escape($user->email),
            $this->escape((string) $user->getKey()),
            $this->escape($termsVersion->version),
            $this->escape($termsVersion->title),
            $this->escape($acceptedAt->format(DATE_ATOM)),
            $this->escape($request->ip() ?? ''),
            $this->escape((string) $request->userAgent()),
            $clauses,
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
