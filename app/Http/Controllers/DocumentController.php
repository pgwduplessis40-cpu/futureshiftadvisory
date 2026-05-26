<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\VerifyDocumentJob;
use App\Models\Client;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\PortalOfflineSync;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly SecureFileWriter $writer,
        private readonly PortalOfflineSync $offlineSync,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $entrepreneurProfile = $this->entrepreneurProfileFor($request);
        if ($entrepreneurProfile instanceof EntrepreneurProfile) {
            return $this->storeForEntrepreneur($request, $entrepreneurProfile);
        }

        if ($this->offlineSync->isSync($request)) {
            return $this->offlineSync->handle(
                $request,
                'portal.documents.store',
                fn (Client $client): JsonResponse => $this->storeForClient($request, $client),
            );
        }

        $client = $this->clients->resolveFor($request);

        return $this->storeForClient($request, $client);
    }

    public function show(Request $request, Document $document): SymfonyResponse
    {
        Gate::authorize('view', $document);
        abort_unless($this->canAccessDocument($request, $document), 403);
        abort_unless($document->isVisibleToClients(), 404);

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

    private function storeForEntrepreneur(Request $request, EntrepreneurProfile $profile): JsonResponse
    {
        $validated = $this->validatedUpload($request);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $document = $this->writer->write(
            uploadedFile: $file,
            owner: $request->user(),
            category: (string) ($validated['category'] ?? Document::CATEGORY_PLAN_ATTACHMENT),
            entrepreneurProfileId: (string) $profile->getKey(),
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

    private function storeForClient(Request $request, Client $client): JsonResponse
    {
        $validated = $this->validatedUpload($request);
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
    private function validatedUpload(Request $request): array
    {
        return $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:80'],
            'question_id' => ['nullable', 'uuid', 'exists:questionnaire_questions,id'],
            'claim_value' => ['nullable', 'string', 'max:4000'],
            'question_prompt' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function entrepreneurProfileFor(Request $request): ?EntrepreneurProfile
    {
        $user = $request->user();
        if (! $user instanceof User || $user->user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    private function canAccessDocument(Request $request, Document $document): bool
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->user_type === User::TYPE_ENTREPRENEUR) {
            $profile = EntrepreneurProfile::query()
                ->where('user_id', $user->getKey())
                ->first();

            return $profile instanceof EntrepreneurProfile
                && (string) $document->entrepreneur_profile_id === (string) $profile->getKey();
        }

        try {
            $client = $this->clients->resolveFor($request);
        } catch (Throwable) {
            return false;
        }

        return (string) $document->client_id === (string) $client->getKey();
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
            'url' => route('portal.documents.show', $document, absolute: false),
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
