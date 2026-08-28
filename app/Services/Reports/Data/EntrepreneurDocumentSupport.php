<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Document-evidence notation shared by entrepreneur assessment sections.
 */
final readonly class EntrepreneurDocumentSupport
{
    public function __construct(
        public string $support,
        public string $note,
        public string $dataQualityIndicator,
    ) {}
}
