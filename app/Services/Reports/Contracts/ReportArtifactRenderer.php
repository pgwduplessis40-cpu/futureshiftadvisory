<?php

declare(strict_types=1);

namespace App\Services\Reports\Contracts;

use App\Models\Report;

/**
 * Renders a composed report into its durable client-facing artifacts.
 */
interface ReportArtifactRenderer
{
    public function render(Report $report, bool $withPptx = false): void;

    public function rerender(Report $report): void;

    public function usesCurrentTemplate(Report $report): bool;
}
