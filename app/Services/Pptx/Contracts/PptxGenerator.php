<?php

declare(strict_types=1);

namespace App\Services\Pptx\Contracts;

use App\Models\Report;

interface PptxGenerator
{
    public function render(Report $report): string;
}
