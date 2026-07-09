<?php

declare(strict_types=1);

namespace App\Services\Terms;

use App\Models\Document;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Reports\UploadedReportTemplateRenderer;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class TermsDocumentRenderer
{
    public function __construct(private readonly UploadedReportTemplateRenderer $docx) {}

    public function sourcePreviewHtml(TermsVersion $version): ?string
    {
        $bytes = $this->sourceDocxBytes($version);

        return $bytes === null ? null : $this->docx->renderStandaloneFragmentFromBytes($bytes);
    }

    public function reviewDownloadHtml(TermsVersion $version): string
    {
        return $this->downloadHtml($version, [
            'Future Shift Advisory',
            'Terms and conditions review copy.',
            'Version '.$version->version.' generated for review on '.now()->toDateTimeString().'.',
        ]);
    }

    public function userDownloadHtml(TermsVersion $version, User $user): string
    {
        return $this->downloadHtml($version, [
            'Future Shift Advisory',
            'Terms and conditions download.',
            'Version '.$version->version.' downloaded by '.$user->email.' on '.now()->toDateTimeString().'.',
        ]);
    }

    public function signedAcceptanceHtml(
        TermsVersion $version,
        User $user,
        Request $request,
        DateTimeInterface $acceptedAt,
    ): string {
        return $this->downloadHtml($version, [
            'Future Shift Advisory',
            'Signed terms and conditions acceptance record.',
            'Accepted by: '.$user->name.' <'.$user->email.'>',
            'User ID: '.$user->getKey(),
            'Terms version: '.$version->version.' - '.$version->title,
            'Accepted at: '.$acceptedAt->format(DATE_ATOM),
            'IP address: '.($request->ip() ?? ''),
            'User agent: '.((string) $request->userAgent()),
        ], 'Signed terms acceptance');
    }

    /**
     * @return array<int, string>
     */
    public function plainTextLines(TermsVersion $version): array
    {
        $source = $this->sourcePreviewHtml($version);

        if ($source !== null) {
            $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $source) ?? $source;
            $withBreaks = preg_replace('/<\/(h1|h2|h3|p|div|header|footer|td|tr|table)>/i', "\n", $withoutStyles) ?? $withoutStyles;
            $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines = collect(preg_split('/\R+/', $text) ?: [])
                ->map(fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line) ?? $line))
                ->filter()
                ->values()
                ->all();

            if ($lines !== []) {
                return $lines;
            }
        }

        return $this->clauseLines($version);
    }

    /**
     * @param  array<int, string>  $metaLines
     */
    private function downloadHtml(TermsVersion $version, array $metaLines, ?string $title = null): string
    {
        $source = $this->sourcePreviewHtml($version);

        if ($source !== null) {
            return $this->htmlDocument($title ?? $version->title, $metaLines, $source);
        }

        return $this->htmlDocument($title ?? $version->title, $metaLines, $this->clausesHtml($version));
    }

    /**
     * @param  array<int, string>  $metaLines
     */
    private function htmlDocument(string $title, array $metaLines, string $content): string
    {
        $meta = collect($metaLines)
            ->map(fn (string $line): string => '<p class="meta">'.$this->escape($line).'</p>')
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.$this->escape($title).'</title>'
            .'<style>body{font-family:Arial,sans-serif;color:#111827;line-height:1.5;margin:0;padding:24px}h1{color:#0f172a}.terms-meta{border-bottom:1px solid #d1d5db;margin-bottom:18px;padding-bottom:12px}.meta{font-size:12px;color:#4b5563;margin:0 0 4px}</style>'
            .'</head><body><div class="terms-meta"><h1>'.$this->escape($title).'</h1>'.$meta.'</div>'
            .$content
            .'</body></html>';
    }

    private function clausesHtml(TermsVersion $version): string
    {
        return $version->clauses
            ->sortBy('clause_number')
            ->map(fn ($clause): string => sprintf(
                '<section><h2>Clause %s: %s%s</h2><p>%s</p></section>',
                $this->escape((string) $clause->clause_number),
                $this->escape($clause->title),
                $clause->material ? ' <span>(material)</span>' : '',
                nl2br($this->escape($clause->body)),
            ))
            ->implode('');
    }

    /**
     * @return array<int, string>
     */
    private function clauseLines(TermsVersion $version): array
    {
        return $version->clauses
            ->sortBy('clause_number')
            ->flatMap(fn ($clause): array => [
                'Clause '.$clause->clause_number.': '.$clause->title.($clause->material ? ' (material)' : ''),
                (string) $clause->body,
            ])
            ->values()
            ->all();
    }

    private function sourceDocxBytes(TermsVersion $version): ?string
    {
        $sourceFile = is_array($version->source_file) ? $version->source_file : null;
        if ($sourceFile === null || ! $this->isDocxSource($sourceFile)) {
            return null;
        }

        if ($this->sourceFileScannerResult($sourceFile) !== Document::SCANNER_CLEAN) {
            return null;
        }

        $path = (string) ($sourceFile['stored_path'] ?? '');
        $disk = Storage::disk('secure_local');

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /**
     * @param  array<string, mixed>  $sourceFile
     */
    private function isDocxSource(array $sourceFile): bool
    {
        $extension = Str::lower((string) ($sourceFile['extension'] ?? ''));
        $mimeType = Str::lower((string) ($sourceFile['mime_type'] ?? ''));
        $originalName = Str::lower((string) ($sourceFile['original_name'] ?? ''));

        return $extension === 'docx'
            || str_contains($mimeType, 'wordprocessingml.document')
            || str_ends_with($originalName, '.docx');
    }

    /**
     * @param  array<string, mixed>  $sourceFile
     */
    private function sourceFileScannerResult(array $sourceFile): string
    {
        $scannerResult = $sourceFile['scanner_result'] ?? null;
        if (is_string($scannerResult) && $scannerResult !== '') {
            return $scannerResult;
        }

        $documentId = $sourceFile['document_id'] ?? null;
        if (is_string($documentId) && $documentId !== '') {
            $document = Document::query()->find($documentId);

            if ($document instanceof Document) {
                return $document->scanner_result;
            }
        }

        return Document::SCANNER_CLEAN;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
