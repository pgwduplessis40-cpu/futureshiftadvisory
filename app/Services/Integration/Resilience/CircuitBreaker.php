<?php

declare(strict_types=1);

namespace App\Services\Integration\Resilience;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class CircuitBreaker
{
    public function isOpen(string $service): bool
    {
        $openUntil = Cache::get($this->openKey($service));

        return is_numeric($openUntil) && (int) $openUntil > now()->getTimestamp();
    }

    public function recordSuccess(string $service): void
    {
        Cache::forget($this->failureKey($service));
        Cache::forget($this->openKey($service));
    }

    public function recordFailure(string $service): void
    {
        if ($this->isOpen($service)) {
            return;
        }

        Cache::add($this->failureKey($service), 0, $this->windowSeconds());
        $failures = (int) Cache::increment($this->failureKey($service));

        if ($failures >= $this->failureThreshold()) {
            Cache::put(
                $this->openKey($service),
                now()->addSeconds($this->openSeconds())->getTimestamp(),
                $this->openSeconds(),
            );
            Cache::forget($this->failureKey($service));
        }
    }

    public function reset(string $service): void
    {
        Cache::forget($this->failureKey($service));
        Cache::forget($this->openKey($service));
    }

    private function failureThreshold(): int
    {
        return max(1, (int) Config::get('integrations.circuit_breaker.failure_threshold', 5));
    }

    private function windowSeconds(): int
    {
        return max(1, (int) Config::get('integrations.circuit_breaker.window_seconds', 60));
    }

    private function openSeconds(): int
    {
        return max(1, (int) Config::get('integrations.circuit_breaker.open_seconds', 300));
    }

    private function failureKey(string $service): string
    {
        return 'fsa:integration-breaker:failures:'.$this->serviceKey($service);
    }

    private function openKey(string $service): string
    {
        return 'fsa:integration-breaker:open:'.$this->serviceKey($service);
    }

    private function serviceKey(string $service): string
    {
        return Str::slug($service) ?: hash('sha256', $service);
    }
}
