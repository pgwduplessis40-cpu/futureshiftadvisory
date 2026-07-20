<?php

declare(strict_types=1);

namespace App\Services\Performance;

use Illuminate\Database\Events\QueryExecuted;

final class DashboardLaunchTiming
{
    private bool $recording = false;

    private int $startedAt = 0;

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    public function start(): void
    {
        $this->recording = true;
        $this->startedAt = hrtime(true);
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
    }

    public function record(QueryExecuted $query): void
    {
        if (! $this->recording) {
            return;
        }

        $this->queryCount++;
        $this->queryTimeMs += $query->time;
    }

    /**
     * @return array{app_ms: float, db_ms: float, db_count: int}
     */
    public function metrics(): array
    {
        $appMs = $this->startedAt === 0
            ? 0.0
            : (hrtime(true) - $this->startedAt) / 1_000_000;

        return [
            'app_ms' => round($appMs, 1),
            'db_ms' => round($this->queryTimeMs, 1),
            'db_count' => $this->queryCount,
        ];
    }
}
