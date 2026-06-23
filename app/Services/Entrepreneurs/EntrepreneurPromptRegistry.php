<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

final class EntrepreneurPromptRegistry
{
    public const EXAMINER = 'examiner';

    public const NON_EXAMINER = 'non_examiner';

    public const PLAN_SCORE_CRITERION = 'entrepreneur.plan_score_criterion';

    public const PLAN_GUIDANCE = 'entrepreneur.plan_guidance';

    public const IDEA_VALIDATION = 'entrepreneur.idea_validation';

    /**
     * @return array<string, self::EXAMINER|self::NON_EXAMINER>
     */
    public static function classifications(): array
    {
        return [
            self::PLAN_SCORE_CRITERION => self::EXAMINER,
            self::PLAN_GUIDANCE => self::NON_EXAMINER,
            self::IDEA_VALIDATION => self::NON_EXAMINER,
        ];
    }
}
