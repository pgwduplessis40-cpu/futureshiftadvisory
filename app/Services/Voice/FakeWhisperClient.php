<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Document;
use App\Services\Voice\Contracts\WhisperClient;
use Illuminate\Support\Facades\Storage;

final class FakeWhisperClient implements WhisperClient
{
    public function transcribe(Document $document): array
    {
        $text = Storage::disk('secure_local')->exists($document->stored_path)
            ? Storage::disk('secure_local')->get($document->stored_path)
            : '';

        return [
            'text' => trim($text) !== '' ? trim($text) : 'Transcription unavailable.',
            'metadata' => [
                'provider' => 'fake-whisper',
                'document_id' => $document->getKey(),
            ],
        ];
    }
}
