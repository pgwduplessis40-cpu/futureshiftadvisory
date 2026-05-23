<?php

declare(strict_types=1);

namespace App\Services\Voice\Contracts;

use App\Models\Document;

interface WhisperClient
{
    /**
     * @return array{text:string, metadata:array<string, mixed>}
     */
    public function transcribe(Document $document): array;
}
