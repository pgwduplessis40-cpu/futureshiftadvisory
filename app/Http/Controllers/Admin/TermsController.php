<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use App\Services\Storage\SecureFileWriter;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Terms\TermsDocumentRenderer;
use App\Services\Terms\TermsPdfFallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TermsController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly TermsAcceptanceGate $gate,
        private readonly TermsDocumentRenderer $documents,
        private readonly TermsPdfFallback $fallbackPdf,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', TermsVersion::class);

        return Inertia::render('admin/terms/Index', [
            'versions' => TermsVersion::query()
                ->withCount([
                    'clauses',
                    'clauses as material_clauses_count' => fn ($query) => $query->where('material', true),
                ])
                ->latest('created_at')
                ->get()
                ->map(fn (TermsVersion $version): array => $this->versionPayload($version)),
            'enforcement' => $this->enforcementPayload(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TermsVersion::class);

        $draft = DB::transaction(function () use ($request): TermsVersion {
            $source = TermsVersion::query()
                ->with('clauses')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->first();

            $draft = TermsVersion::query()->create([
                'version' => $this->nextVersion(),
                'title' => $source?->title ?? 'Future Shift Advisory Terms and Conditions',
                'material' => false,
                'notice_period_days' => $source?->notice_period_days ?? 30,
                'reviewer_reference' => $source?->reviewer_reference,
                'source_file' => $source?->source_file,
                'created_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            $source?->clauses->each(function ($clause) use ($draft): void {
                $draft->clauses()->create([
                    'clause_number' => $clause->clause_number,
                    'title' => $clause->title,
                    'body' => $clause->body,
                    'material' => $clause->material,
                ]);
            });

            return $draft;
        });

        return to_route('admin.terms.edit', $draft);
    }

    public function edit(TermsVersion $termsVersion): Response
    {
        Gate::authorize('update', $termsVersion);

        return Inertia::render('admin/terms/Edit', [
            'version' => $this->versionPayload($termsVersion->load('clauses')),
        ]);
    }

    public function update(Request $request, TermsVersion $termsVersion): RedirectResponse
    {
        Gate::authorize('update', $termsVersion);
        abort_if($termsVersion->isPublished(), 422, 'Published terms versions are immutable.');

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('terms_versions', 'version')->ignore($termsVersion->id)],
            'title' => ['required', 'string', 'max:255'],
            'material' => ['required', 'boolean'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'reviewer_reference' => ['nullable', 'string', 'max:2000'],
            'clauses' => ['required', 'array', 'size:14'],
            'clauses.*.id' => ['nullable', 'uuid'],
            'clauses.*.clause_number' => ['required', 'integer', 'min:1', 'max:99', 'distinct'],
            'clauses.*.title' => ['required', 'string', 'max:255'],
            'clauses.*.body' => ['required', 'string'],
            'clauses.*.material' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($termsVersion, $validated): void {
            $termsVersion->update([
                'version' => $validated['version'],
                'title' => $validated['title'],
                'material' => $validated['material'],
                'notice_period_days' => $validated['notice_period_days'],
                'reviewer_reference' => $validated['reviewer_reference'] ?? null,
            ]);

            $seen = [];
            foreach ($validated['clauses'] as $clause) {
                $saved = $termsVersion->clauses()->updateOrCreate(
                    ['clause_number' => $clause['clause_number']],
                    [
                        'title' => $clause['title'],
                        'body' => $clause['body'],
                        'material' => $clause['material'],
                    ],
                );
                $seen[] = $saved->getKey();
            }

            $termsVersion->clauses()
                ->whereNotIn('id', $seen)
                ->delete();
        });

        return to_route('admin.terms.edit', $termsVersion)->with('status', 'terms-updated');
    }

    public function preview(TermsVersion $termsVersion): Response
    {
        Gate::authorize('view', $termsVersion);

        return Inertia::render('admin/terms/Preview', [
            'version' => $this->versionPayload($termsVersion->load('clauses'), includeSourcePreview: true),
        ]);
    }

    public function download(Request $request, TermsVersion $termsVersion, PdfRenderer $renderer): HttpResponse
    {
        Gate::authorize('view', $termsVersion);

        $termsVersion->load('clauses');
        $html = $this->documents->reviewDownloadHtml($termsVersion);
        try {
            $pdf = $renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->fallbackPdf->reviewDownload($termsVersion);
        }
        $filename = Str::slug('future-shift-advisory-terms-'.$termsVersion->version).'.pdf';

        $this->auditWriter->record('terms.downloaded_for_review', subject: $termsVersion, actor: $request->user(), after: [
            'version' => $termsVersion->version,
            'byte_size' => strlen($pdf),
            'published_at' => $termsVersion->published_at?->toIso8601String(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function uploadSourceFile(Request $request, TermsVersion $termsVersion, SecureFileWriter $files): RedirectResponse
    {
        Gate::authorize('update', $termsVersion);
        abort_if($termsVersion->isPublished(), 422, 'Published terms versions are immutable.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:doc,docx', 'max:20480'],
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $document = $files->write($file, $request->user(), Document::CATEGORY_TEMPLATE_FILE);
        } catch (InfectedFileException) {
            return back()->withErrors(['file' => 'Upload rejected because malware was detected.']);
        } catch (SecureFileStorageException $exception) {
            report($exception);

            return back()->withErrors(['file' => 'Upload could not be stored securely. Please try again or contact support.']);
        }

        $termsVersion->forceFill([
            'source_file' => [
                'document_id' => (string) $document->getKey(),
                'stored_path' => $document->stored_path,
                'original_name' => $document->original_filename,
                'mime_type' => $document->mime_type ?: 'application/octet-stream',
                'extension' => strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'docx'),
                'byte_size' => $document->byte_size,
                'sha256' => $document->sha256,
                'scanner_result' => $document->scanner_result,
                'uploaded_at' => now()->toIso8601String(),
            ],
        ])->save();

        $this->auditWriter->record('terms.source_file_uploaded', subject: $termsVersion, actor: $request->user(), after: [
            'terms_version_id' => $termsVersion->getKey(),
            'filename' => $document->original_filename,
            'byte_size' => $document->byte_size,
        ]);

        return back()->with('status', 'terms-source-file-uploaded');
    }

    public function downloadSourceFile(Request $request, TermsVersion $termsVersion): HttpResponse
    {
        Gate::authorize('view', $termsVersion);

        $sourceFile = $this->sourceFile($termsVersion);
        abort_if($sourceFile === null, 404);
        abort_if(! $this->sourceFileIsClean($sourceFile), 404);

        $path = (string) ($sourceFile['stored_path'] ?? '');
        $disk = Storage::disk('secure_local');
        abort_if($path === '' || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $this->auditWriter->record('terms.source_file_downloaded', subject: $termsVersion, actor: $request->user(), after: [
            'terms_version_id' => $termsVersion->getKey(),
            'filename' => $sourceFile['original_name'] ?? null,
        ]);

        $filename = $this->downloadFilename((string) ($sourceFile['original_name'] ?? 'terms-source.docx'));
        $mime = (string) ($sourceFile['mime_type'] ?? 'application/octet-stream');

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function confirmPublish(TermsVersion $termsVersion): Response
    {
        Gate::authorize('publish', $termsVersion);

        return Inertia::render('admin/terms/Publish', [
            'version' => $this->versionPayload($termsVersion->load('clauses')),
        ]);
    }

    public function publish(Request $request, TermsVersion $termsVersion): RedirectResponse
    {
        Gate::authorize('publish', $termsVersion);
        abort_if($termsVersion->isPublished(), 422, 'This terms version has already been published.');

        $validated = $request->validate([
            'material' => ['required', 'boolean'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'reviewer_reference' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $termsVersion, $validated): void {
            $publishedAt = now();
            $prior = TermsVersion::query()
                ->published()
                ->whereKeyNot($termsVersion->getKey())
                ->orderByDesc('published_at')
                ->first();

            $termsVersion->forceFill([
                'material' => $validated['material'],
                'notice_period_days' => $validated['notice_period_days'],
                'reviewer_reference' => $validated['reviewer_reference'] ?? null,
                'published_at' => $publishedAt,
                'published_by_user_id' => $request->user()?->getAuthIdentifier(),
            ])->save();

            $queued = 0;
            if ($termsVersion->material && $prior instanceof TermsVersion) {
                $expiresAt = $publishedAt->copy()->addDays($termsVersion->notice_period_days);
                $queued = TermsAcceptance::query()
                    ->where('terms_version_id', $prior->getKey())
                    ->active()
                    ->update([
                        'expires_at' => $expiresAt,
                        'reacceptance_notice_queued_at' => $publishedAt,
                    ]);

                $this->auditWriter->record('terms.reacceptance_queued', subject: $termsVersion, after: [
                    'prior_terms_version_id' => $prior->getKey(),
                    'new_terms_version_id' => $termsVersion->getKey(),
                    'expires_at' => $expiresAt->toIso8601String(),
                    'users_queued' => $queued,
                ]);
            }

            $this->auditWriter->record('terms.published', subject: $termsVersion, after: [
                'version' => $termsVersion->version,
                'material' => $termsVersion->material,
                'notice_period_days' => $termsVersion->notice_period_days,
                'published_at' => $publishedAt->toIso8601String(),
                'reacceptance_users_queued' => $queued,
            ]);
        });

        return to_route('admin.terms.preview', $termsVersion)->with('status', 'terms-published');
    }

    public function activateEnforcement(Request $request): RedirectResponse
    {
        Gate::authorize('publish', TermsVersion::class);

        $latest = $this->gate->latestPublishedVersion();
        abort_unless($latest instanceof TermsVersion, 422, 'Publish a terms version before activating enforcement.');
        abort_if($this->gate->isEnforced(), 422, 'Terms enforcement has already been activated.');

        DB::transaction(function () use ($latest, $request): TermsEnforcement {
            $activation = TermsEnforcement::query()->create([
                'scope' => TermsEnforcement::SCOPE_PLATFORM,
                'activated_at' => now(),
                'activated_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            $this->auditWriter->record('terms.enforcement_activated', subject: $activation, actor: $request->user(), after: [
                'latest_terms_version_id' => $latest->getKey(),
                'latest_terms_version' => $latest->version,
                'activated_at' => $activation->activated_at?->toIso8601String(),
            ]);

            return $activation;
        });

        return to_route('admin.terms.index')->with('status', 'terms-enforcement-activated');
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(TermsVersion $version, bool $includeSourcePreview = false): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'title' => $version->title,
            'material' => $version->material,
            'notice_period_days' => $version->notice_period_days,
            'reviewer_reference' => $version->reviewer_reference,
            'published_at' => $version->published_at?->toIso8601String(),
            'published_by_user_id' => $version->published_by_user_id,
            'source_file' => $this->sourceFilePayload($version),
            'source_download_url' => ! $this->sourceFileIsClean($this->sourceFile($version))
                ? null
                : route('admin.terms.source-file.download', $version, absolute: false),
            'source_preview_html' => $includeSourcePreview ? $this->documents->sourcePreviewHtml($version) : null,
            'clauses_count' => $version->clauses_count,
            'material_clauses_count' => $version->relationLoaded('clauses')
                ? $version->clauses->where('material', true)->count()
                : $version->material_clauses_count,
            'clauses' => $version->relationLoaded('clauses')
                ? $version->clauses->map(fn ($clause): array => [
                    'id' => $clause->id,
                    'clause_number' => $clause->clause_number,
                    'title' => $clause->title,
                    'body' => $clause->body,
                    'material' => $clause->material,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enforcementPayload(): array
    {
        $enforcement = $this->gate->enforcement();
        $latest = $this->gate->latestPublishedVersion();

        return [
            'active' => $enforcement instanceof TermsEnforcement,
            'activated_at' => $enforcement?->activated_at?->toIso8601String(),
            'activated_by' => $enforcement?->activatedBy ? [
                'id' => $enforcement->activatedBy->id,
                'name' => $enforcement->activatedBy->name,
            ] : null,
            'can_activate' => ! ($enforcement instanceof TermsEnforcement) && $latest instanceof TermsVersion,
            'latest_published_version' => $latest instanceof TermsVersion ? [
                'id' => $latest->id,
                'version' => $latest->version,
                'published_at' => $latest->published_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function nextVersion(): string
    {
        $versions = TermsVersion::query()->pluck('version');
        $next = $versions
            ->map(fn (string $version): int => (int) preg_replace('/\D+/', '', $version))
            ->max() + 1;

        return (string) max(1, $next);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceFile(TermsVersion $version): ?array
    {
        return is_array($version->source_file) ? $version->source_file : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceFilePayload(TermsVersion $version): ?array
    {
        $sourceFile = $this->sourceFile($version);

        if ($sourceFile === null) {
            return null;
        }

        return [
            'original_name' => $sourceFile['original_name'] ?? null,
            'mime_type' => $sourceFile['mime_type'] ?? null,
            'byte_size' => $sourceFile['byte_size'] ?? null,
            'scanner_result' => $this->sourceFileScannerResult($sourceFile),
            'is_quarantined' => ! $this->sourceFileIsClean($sourceFile),
            'uploaded_at' => $sourceFile['uploaded_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sourceFile
     */
    private function sourceFileIsClean(?array $sourceFile): bool
    {
        return $sourceFile !== null
            && $this->sourceFileScannerResult($sourceFile) === Document::SCANNER_CLEAN;
    }

    /**
     * @param  array<string, mixed>  $sourceFile
     */
    private function sourceFileScannerResult(array $sourceFile): string
    {
        $scannerResult = $sourceFile['scanner_result'] ?? null;
        if (is_string($scannerResult) && $scannerResult !== '') {
            return $scannerResult;
        }

        $documentId = $sourceFile['document_id'] ?? null;
        if (is_string($documentId) && $documentId !== '') {
            $document = Document::query()->find($documentId);

            if ($document instanceof Document) {
                return $document->scanner_result;
            }
        }

        return Document::SCANNER_CLEAN;
    }

    private function downloadFilename(string $filename): string
    {
        return str_replace(['\\', '/', '"', "\r", "\n"], '-', $filename);
    }
}
