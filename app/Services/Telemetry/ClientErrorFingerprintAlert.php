<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Support\Telemetry\ClientErrorEvent;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;

final class ClientErrorFingerprintAlert
{
    public function __construct(private readonly Repository $cache) {}

    public function observe(ClientErrorEvent $event): void
    {
        $key = 'client-error-fingerprint:'.$event->releaseSha.':'.$event->errorFingerprint;

        if (! $this->cache->add($key, true, now()->addDays(14))) {
            return;
        }

        // Production log routing treats this event as an alert. The context is
        // the DTO's closed contract only, so log aggregation cannot receive
        // PII, request payloads, financial values, or document content.
        Log::channel((string) config('telemetry.client_error.channel', 'stack'))
            ->warning('client_error_fingerprint.new', $event->toArray());
    }
}
