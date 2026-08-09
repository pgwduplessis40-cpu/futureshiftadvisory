<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ClientDocumentController extends Controller
{
    public function show(Client $client, Document $document): SymfonyResponse
    {
        Gate::authorize('view', $client);
        abort_unless((string) $document->client_id === (string) $client->getKey(), 404);
        abort_unless($document->scanner_result === Document::SCANNER_CLEAN, 404);

        $disk = Storage::disk('secure_local');
        abort_unless($disk->exists($document->stored_path), 404);

        $disposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->original_filename,
            Str::ascii($document->original_filename) ?: 'document',
        );

        return response($disk->get($document->stored_path), 200, [
            'Content-Disposition' => $disposition,
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
