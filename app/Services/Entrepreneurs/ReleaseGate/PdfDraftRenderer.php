<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Pdf\PdfRenderer;
use Throwable;

final readonly class PdfDraftRenderer
{
    public function __construct(private PdfRenderer $pdf) {}

    /** @param callable():string $fallback */
    public function render(string $html, callable $fallback): RenderedDraft
    {
        try {
            return new RenderedDraft($this->pdf->render($html), 'primary', $html);
        } catch (Throwable $exception) {
            report($exception);

            return new RenderedDraft($fallback(), 'fallback');
        }
    }
}
