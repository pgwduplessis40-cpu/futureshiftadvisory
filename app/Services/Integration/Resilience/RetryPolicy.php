<?php

declare(strict_types=1);

namespace App\Services\Integration\Resilience;

use Illuminate\Support\Facades\Config;

final readonly class RetryPolicy
{
    /**
     * @param  array<int, int>  $retryStatuses
     */
    public function __construct(
        public int $attempts,
        public int $baseDelayMs,
        public int $maxDelayMs,
        public array $retryStatuses,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            attempts: max(1, (int) Config::get('integrations.retry.attempts', 3)),
            baseDelayMs: max(0, (int) Config::get('integrations.retry.base_delay_ms', 100)),
            maxDelayMs: max(0, (int) Config::get('integrations.retry.max_delay_ms', 1000)),
            retryStatuses: array_map('intval', (array) Config::get('integrations.retry.retry_statuses', [])),
        );
    }

    public function shouldRetryStatus(int $statusCode, int $attempt): bool
    {
        if ($attempt >= $this->attempts) {
            return false;
        }

        return in_array($statusCode, $this->retryStatuses, true) || $statusCode >= 500;
    }

    public function shouldRetryException(int $attempt): bool
    {
        return $attempt < $this->attempts;
    }

    public function delayMsForAttempt(int $attempt): int
    {
        if ($this->baseDelayMs === 0) {
            return 0;
        }

        $delay = $this->baseDelayMs * (2 ** max(0, $attempt - 1));

        return min($delay, $this->maxDelayMs);
    }
}
