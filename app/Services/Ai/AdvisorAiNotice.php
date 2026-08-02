<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiUsageEvent;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AdvisorAiNotice
{
    public const CACHE_KEY = 'fsa.ai.provider.unavailable.latest_notice.v2';

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

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $payload = Cache::get(self::CACHE_KEY);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Return only an unresolved provider failure suitable for staff action.
     *
     * @return array<string, mixed>|null
     */
    public function actionable(): ?array
    {
        $latest = $this->latest();

        if ($latest === null) {
            return null;
        }

        if ($this->supersededBySuccessfulAiResponse($latest)) {
            $this->clear();

            return null;
        }

        $reason = (string) ($latest['reason'] ?? '');
        if (
            str_contains($reason, 'not active or its credentials are missing')
            && app(AiProviderManager::class)->activeProviderIsLive()
        ) {
            $this->clear();

            return null;
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $notice
     */
    private function supersededBySuccessfulAiResponse(array $notice): bool
    {
        if (! Schema::hasTable('ai_usage_events')) {
            return false;
        }

        $recordedAt = data_get($notice, 'recorded_at');
        if (! is_string($recordedAt) || trim($recordedAt) === '') {
            return false;
        }

        try {
            $recordedAt = CarbonImmutable::parse($recordedAt);
        } catch (Throwable) {
            return false;
        }

        return AiUsageEvent::query()
            ->where('provider', app(AiProviderManager::class)->activeProviderKey())
            ->where('occurred_at', '>=', $recordedAt)
            ->exists();
    }
}
