<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Services\Ai\Contracts\Uncertainty;

final readonly class AnalysisFindingData
{
    /**
     * @param  array<int, array{claim:string, source_reference:string}>|null  $attributions
     * @param  array<int, array<string, mixed>>|null  $biasSignals
     */
    public function __construct(
        public AnalysisLens $lens,
        public FindingSeverity $severity,
        public string $title,
        public string $body,
        public ?array $attributions = null,
        public string $documentSupport = AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        public ?Uncertainty $uncertainty = null,
        public ?string $dataQualityDisclaimer = null,
        public ?array $biasSignals = null,
        public ?string $pvLinkId = null,
    ) {}
}
