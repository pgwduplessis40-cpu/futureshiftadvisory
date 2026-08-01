<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Throwable;

final class ResilientPdfPreviewRenderer
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly SimpleTextPdf $fallback,
    ) {}

    public function render(string $html, string $fallbackTitle): string
    {
        try {
            return $this->renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback->render($fallbackTitle, [
                'Browser-formatted PDF generation was temporarily unavailable. This fallback preserves the current document content.',
                ...$this->paragraphs($html),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function paragraphs(string $html): array
    {
        $withoutAssets = preg_replace('/<(style|script|template)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $withBreaks = preg_replace('/<\/?(?:address|article|aside|blockquote|br|div|footer|h[1-6]|header|hr|li|main|p|section|table|tr)\b[^>]*>/i', "\n", $withoutAssets) ?? $withoutAssets;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;

        return collect(preg_split('/\R+/', $text) ?: [])
            ->map(static fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->values()
            ->all();
    }
}
