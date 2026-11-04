<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait AppendOnlyPlanGateRecord
{
    protected static function bootAppendOnlyPlanGateRecord(): void
    {
        static::updating(fn () => throw new \LogicException('Release-gate records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Release-gate records are append-only.'));
    }
}
