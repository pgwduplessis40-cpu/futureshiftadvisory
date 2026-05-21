<?php

declare(strict_types=1);

namespace App\Services\Pdf;

interface PdfRenderer
{
    public function render(string $html): string;
}
