<?php

declare(strict_types=1);

namespace App\Services\Reports\Data;

/**
 * A normalized succession option safe for report rendering and metadata.
 */
final readonly class SuccessionOption
{
    public function __construct(
        public string $name,
        public string $fitScore,
        public string $rationale,
    ) {}

    public function line(): string
    {
        return sprintf('%s: fit score %s. %s', $this->name, $this->fitScore, $this->rationale);
    }
}
