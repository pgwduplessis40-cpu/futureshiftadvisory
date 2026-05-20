<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AdvisorAiNotice
{
    public const CACHE_KEY = 'fsa.ai.unavailable.latest_notice';

    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function recordUnavailable(PromptEnvelope $prompt, string $reason): void
    {
        $payload = [
            'message' => Fake\FakeAiClient::DEGRADED_TEXT,
            'reason' => $reason,
            'prompt_id' => $prompt->id,
            'prompt_hash' => $prompt->hash(),
            'recorded_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $payload, now()->addDay());
        Log::notice('ai.unavailable', $payload);

        try {
            $this->auditWriter->record(
                action: 'ai.unavailable',
                subject: null,
                after: $payload,
            );
        } catch (Throwable $e) {
            Log::warning('Failed to persist AI unavailable audit event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $payload = Cache::get(self::CACHE_KEY);

        return is_array($payload) ? $payload : null;
    }
}
