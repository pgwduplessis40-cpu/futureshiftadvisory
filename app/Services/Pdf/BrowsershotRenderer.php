<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

final class BrowsershotRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        $shot = Browsershot::html($html)
            ->format('A4')
            ->margins(18, 16, 18, 16)
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

        return $shot->pdf();
    }
}
