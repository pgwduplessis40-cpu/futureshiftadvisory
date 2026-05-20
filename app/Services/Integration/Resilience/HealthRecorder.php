<?php

declare(strict_types=1);

namespace App\Services\Integration\Resilience;

use App\Models\IntegrationCall;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class HealthRecorder
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $errorPayload
     */
    public function record(
        string $service,
        string $endpoint,
        string $status,
        int $attempt,
        ?int $latencyMs = null,
        ?array $errorPayload = null,
        ?string $correlationId = null,
    ): IntegrationCall {
        return IntegrationCall::query()->create([
            'service' => $service,
            'endpoint' => $endpoint,
            'request_id' => $this->requestId(),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'attempt' => $attempt,
            'error_payload' => $errorPayload,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'occurred_at' => now(),
        ]);
    }

    private function requestId(): ?string
    {
        $candidate = $this->request->attributes->get('fsa.request_id')
            ?? $this->request->headers->get('X-Request-Id');

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : null;
    }
}
