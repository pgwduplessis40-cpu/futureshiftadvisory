<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

final class BrowsershotRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        [$html, $footer] = $this->extractPdfFooter($html);

        $shot = Browsershot::html($html)
            ->format('A4')
            ->margins(18, 16, $footer === null ? 18 : 32, 16)
            ->showBackground()
            ->noSandbox()
            ->timeout((int) config('services.browsershot.timeout_seconds', 60));

        $nodeBinary = config('services.browsershot.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $shot->setNodeBinary($nodeBinary);
        }

        $npmBinary = config('services.browsershot.npm_binary');
        if (is_string($npmBinary) && $npmBinary !== '') {
            $shot->setNpmBinary($npmBinary);
        }

        $chromePath = config('services.browsershot.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $shot->setChromePath($chromePath);
        }

        if ($footer !== null) {
            $shot
                ->showBrowserHeaderAndFooter()
                ->hideHeader()
                ->footerHtml($footer);
        }

        return $shot->pdf();
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function extractPdfFooter(string $html): array
    {
        if (preg_match('/<template\s+data-pdf-footer[^>]*>(.*?)<\/template>/is', $html, $matches) !== 1) {
            return [$html, null];
        }

        $footer = trim($matches[1]);
        $html = preg_replace('/<template\s+data-pdf-footer[^>]*>.*?<\/template>/is', '', $html, 1) ?? $html;

        return [$html, $footer === '' ? null : $footer];
    }
}
