<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\VerifyDocumentJob;
use App\Models\Document;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly SecureFileWriter $writer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $client = $this->clients->resolveFor($request);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:80'],
            'question_id' => ['nullable', 'uuid', 'exists:questionnaire_questions,id'],
            'claim_value' => ['nullable', 'string', 'max:4000'],
            'question_prompt' => ['nullable', 'string', 'max:4000'],
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $document = $this->writer->write(
            uploadedFile: $file,
            owner: $request->user(),
            category: (string) ($validated['category'] ?? Document::CATEGORY_OTHER),
            clientId: (string) $client->getKey(),
        );

        VerifyDocumentJob::dispatch((string) $document->getKey(), [
            'question_id' => $validated['question_id'] ?? null,
            'claim_value' => $validated['claim_value'] ?? null,
            'question_prompt' => $validated['question_prompt'] ?? null,
        ]);

        return response()->json([
            'document' => $this->documentPayload($document->refresh()->load('verifications')),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(Document $document): array
    {
        return [
            'id' => $document->id,
            'original_filename' => $document->original_filename,
            'category' => $document->category,
            'scanner_result' => $document->scanner_result,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'verifications' => $document->verifications
                ->map(fn ($verification): array => [
                    'id' => $verification->id,
                    'outcome' => $verification->outcome,
                    'claim_text' => $verification->claim_text,
                    'client_explanation' => $verification->clientFacingExplanation(),
                    'resolved_at' => $verification->resolved_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
