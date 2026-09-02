<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class StrategicBudgetRevisionConflict extends RuntimeException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('This Business Plan & Budget was changed in another session. Refresh before saving again.');
    }
}
