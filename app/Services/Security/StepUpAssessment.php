<?php

declare(strict_types=1);

namespace App\Services\Security;

final readonly class StepUpAssessment
{
    /**
     * @param  array<string, mixed>  $signals
     */
    public function __construct(
        public int $score,
        public int $threshold,
        public array $signals,
    ) {}

    public function requiresStepUp(): bool
    {
        return $this->score >= $this->threshold;
    }
}
