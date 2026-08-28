<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * Typed assessment criterion used to render entrepreneur report sections.
 *
 * @phpstan-type Attribution array{claim: string, source_reference: string}
 */
final readonly class EntrepreneurAssessmentCriterion
{
    /** @param list<Attribution> $attributions */
    public function __construct(
        public string $id,
        public int $number,
        public string $name,
        public float $weight,
        public ?int $aiScore,
        public ?int $advisorScore,
        public int $score,
        public string $grade,
        public string $rationale,
        public array $attributions,
    ) {}
}
