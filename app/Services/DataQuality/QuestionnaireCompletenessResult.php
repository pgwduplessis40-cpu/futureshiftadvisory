<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

final readonly class QuestionnaireCompletenessResult
{
    public function __construct(
        public int $answered,
        public int $expected,
        public int $score,
    ) {}
}
