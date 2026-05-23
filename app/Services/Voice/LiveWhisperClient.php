<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Document;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Voice\Contracts\WhisperClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class LiveWhisperClient implements WhisperClient
{
    public function __construct(private readonly ResilientHttp $http) {}

    public function transcribe(Document $document): array
    {
        $bytes = Storage::disk('secure_local')->get($document->stored_path);
        $endpoint = (string) Config::get('services.whisper.endpoint', 'https://api.openai.com/v1/audio/transcriptions');

        $result = $this->http->post(
            service: 'openai_whisper',
            endpoint: $endpoint,
            payload: [
                'filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'audio_base64' => base64_encode($bytes),
            ],
            fallback: fn (): array => [
                'text' => 'Transcription unavailable.',
                'degraded' => true,
            ],
        );

        $payload = $result->json();
        $payload = is_array($payload) ? $payload : [];

        return [
            'text' => is_string($payload['text'] ?? null) ? $payload['text'] : 'Transcription unavailable.',
            'metadata' => [
                'provider' => 'openai_whisper',
                'correlation_id' => $result->correlationId,
                'degraded' => (bool) ($payload['degraded'] ?? false),
            ],
        ];
    }
}
