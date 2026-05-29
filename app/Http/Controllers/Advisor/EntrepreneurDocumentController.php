<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class EntrepreneurDocumentController extends Controller
{
    public function show(EntrepreneurProfile $entrepreneurProfile, Document $document): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);
        abort_unless((string) $document->entrepreneur_profile_id === (string) $entrepreneurProfile->getKey(), 404);
        abort_if($document->scanner_result === Document::SCANNER_INFECTED, 404);

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
