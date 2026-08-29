<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * A single DD-derived requirement used to assess a post-acquisition business plan.
 */
final readonly class PostAcquisitionPlanRequirement
{
    public function __construct(
        public string $phaseTitle,
        public string $title,
        public bool $complete,
    ) {}

    public function label(): string
    {
        return $this->phaseTitle.': '.$this->title;
    }
}
