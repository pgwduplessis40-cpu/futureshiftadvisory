<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Jobs\VerifyDocumentJob;
use App\Models\Client;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\PortalOfflineSync;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly SecureFileWriter $writer,
        private readonly PortalOfflineSync $offlineSync,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
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

        try {
            $contents = $disk->get($document->stored_path);
        } catch (UnableToReadFile $exception) {
            report($exception);
            abort(404);
        }

        $disposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->original_filename,
            Str::ascii($document->original_filename) ?: 'document',
        );

        return response($contents, 200, [
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

        try {
            $document = $this->writer->write(
                uploadedFile: $file,
                owner: $request->user(),
                category: (string) ($validated['category'] ?? Document::CATEGORY_PLAN_ATTACHMENT),
                entrepreneurProfileId: (string) $profile->getKey(),
            );
        } catch (InfectedFileException) {
            abort(422, 'Upload rejected because malware was detected.');
        } catch (SecureFileStorageException $exception) {
            report($exception);
            abort(422, 'Upload could not be stored securely. Please try again or contact your advisor.');
        }

        $this->dispatchVerificationIfClean($document, $validated);

        return response()->json([
            'document' => $this->documentPayload($document->refresh()->load('verifications')),
        ], 201);
    }

    private function storeForClient(Request $request, Client $client): JsonResponse
    {
        $validated = $this->validatedUpload($request);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $this->assertDdDataRoomUploadAllowed($client, $request->user());

        try {
            $document = $this->writer->write(
                uploadedFile: $file,
                owner: $request->user(),
                category: (string) ($validated['category'] ?? Document::CATEGORY_OTHER),
                clientId: (string) $client->getKey(),
                npoEngagementId: $this->npoEngagementIdForUpload($client, (string) ($validated['category'] ?? Document::CATEGORY_OTHER)),
            );
        } catch (InfectedFileException) {
            abort(422, 'Upload rejected because malware was detected.');
        } catch (SecureFileStorageException $exception) {
            report($exception);
            abort(422, 'Upload could not be stored securely. Please try again or contact your advisor.');
        }

        $this->dispatchVerificationIfClean($document, $validated);

        if ($document->scanner_result === Document::SCANNER_CLEAN) {
            $this->syncDdDataRoomItem($client, $document, $request->user(), $validated);
        }

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
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt'],
            'category' => ['nullable', 'string', 'max:80'],
            'workstream' => ['nullable', 'string', 'max:80'],
            'question_id' => ['nullable', 'uuid', 'exists:questionnaire_questions,id'],
            'claim_value' => ['nullable', 'string', 'max:4000'],
            'question_prompt' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dispatchVerificationIfClean(Document $document, array $validated): void
    {
        if ($document->scanner_result !== Document::SCANNER_CLEAN) {
            return;
        }

        VerifyDocumentJob::dispatch((string) $document->getKey(), [
            'question_id' => $validated['question_id'] ?? null,
            'claim_value' => $validated['claim_value'] ?? null,
            'question_prompt' => $validated['question_prompt'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncDdDataRoomItem(Client $client, Document $document, mixed $actor, array $validated): void
    {
        $engagement = $this->ddEngagementFor($client);

        if (! $engagement instanceof DdEngagement) {
            return;
        }

        app(DataRoom::class)->assertActivated($engagement, $actor instanceof User ? $actor : null);

        $workstream = $this->ddWorkstream((string) ($validated['workstream'] ?? 'financial'));

        DdDataRoomItem::query()->updateOrCreate(
            [
                'dd_engagement_id' => $engagement->getKey(),
                'document_id' => $document->getKey(),
            ],
            [
                'client_id' => $client->getKey(),
                'workstream' => $workstream,
                'folder' => 'client_portal',
                'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
                'source' => DdDataRoomItem::SOURCE_CLIENT_UPLOAD,
                'dd_guest_link_id' => null,
                'guest_name' => null,
                'guest_email' => null,
                'metadata' => [
                    'source' => 'client_portal_upload',
                    'category' => $document->category,
                    'claim_value' => $validated['claim_value'] ?? null,
                    'question_prompt' => $validated['question_prompt'] ?? null,
                    'uploaded_by_user_id' => $actor instanceof User ? $actor->getKey() : null,
                ],
            ],
        );

        app(DdAdviceReportGenerator::class)->generateIfReady($engagement, $actor instanceof User ? $actor : null);
    }

    private function assertDdDataRoomUploadAllowed(Client $client, mixed $actor): void
    {
        $engagement = $this->ddEngagementFor($client);

        if (! $engagement instanceof DdEngagement) {
            return;
        }

        app(DataRoom::class)->assertActivated($engagement, $actor instanceof User ? $actor : null);
    }

    private function ddEngagementFor(Client $client): ?DdEngagement
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::DUE_DILIGENCE) {
            return null;
        }

        $engagement = DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();

        return $engagement instanceof DdEngagement ? $engagement : null;
    }

    private function ddWorkstream(string $value): string
    {
        $normalised = Str::of($value)->lower()->replace(['-', ' '], '_')->toString();

        return array_key_exists($normalised, DataRoom::WORKSTREAMS)
            ? $normalised
            : 'financial';
    }

    private function entrepreneurProfileFor(Request $request): ?EntrepreneurProfile
    {
        $user = $request->user();
        if (! $user instanceof User || $user->user_type !== User::TYPE_ENTREPRENEUR) {
            return null;
        }

        $this->entrepreneurInvites->reconcile($user);

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
            $this->entrepreneurInvites->reconcile($user);

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

    private function npoEngagementIdForUpload(Client $client, string $category): ?string
    {
        $normalisedCategory = str($category)->lower()->replace(['-', ' '], '_')->toString();
        if (! in_array($normalisedCategory, [
            Document::CATEGORY_NPO_MEETING_MINUTES,
            Document::CATEGORY_NPO_BOARD_RECORD,
        ], true)) {
            return null;
        }

        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();

        return $engagement instanceof NpoEngagement ? (string) $engagement->getKey() : null;
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
            'verification_state' => $document->verifications->first()?->outcome
                ?? DocumentVerification::OUTCOME_PENDING,
            'client_explanation' => $document->verifications->first()?->clientFacingExplanation()
                ?? ($document->scanner_result === Document::SCANNER_ERROR
                    ? 'This document is quarantined because the malware scanner could not complete. Your advisor has been alerted.'
                    : 'Verification is in progress.'),
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
