<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TemplateController extends Controller
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FileScanner $scanner,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Template::class);

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', Template::STATUS_ACTIVE));
        $status = in_array($status, Template::libraryStatuses(), true) ? $status : 'all';

        $templates = Template::query()
            ->library()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Template $template): array => $this->templateSummary($template))
            ->values()
            ->all();

        return Inertia::render('advisor/templates/Index', [
            'templates' => $templates,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'categories' => Template::categoryOptions(),
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => Template::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Template::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'canManage' => Gate::allows('create', Template::class),
            'indexUrl' => route('advisor.templates.index', absolute: false),
            'storeUrl' => route('advisor.templates.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Template::class);
        $user = $this->viewer($request);
        $validated = $this->validated($request);

        /** @var Template $template */
        $template = Template::query()->create([
            ...$this->templateAttributes($validated),
            'structure' => [
                'source_kind' => 'manual',
                'sections' => [],
                ...$this->uploadedFileStructure($request),
            ],
            'source_reference' => 'manual:user:'.$user->getKey(),
            'version' => 1,
            'created_by_user_id' => $user->getKey(),
            'learning_update_implementation_id' => null,
        ]);

        $this->audit->record('template.created', subject: $template, actor: $user, after: [
            'template_id' => $template->getKey(),
            'category' => $template->category,
            'status' => $template->status,
            'has_upload' => $this->uploadedFile($template) !== null,
        ]);

        return to_route('advisor.templates.show', $template)->with('status', 'template-created');
    }

    public function show(Template $template): Response
    {
        Gate::authorize('view', $template);
        abort_if($template->status === Template::STATUS_DRAFT, 404);

        return Inertia::render('advisor/templates/Show', [
            'template' => $this->templateDetail($template->loadMissing('creator')),
            'categories' => Template::categoryOptions(),
            'statuses' => [
                ['value' => Template::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Template::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'canManage' => Gate::allows('update', $template),
            'indexUrl' => route('advisor.templates.index', absolute: false),
        ]);
    }

    public function download(Request $request, Template $template): HttpResponse
    {
        Gate::authorize('view', $template);
        abort_if($template->status === Template::STATUS_DRAFT, 404);

        $user = $this->viewer($request);
        $uploadedFile = $this->uploadedFile($template);
        abort_if($uploadedFile === null, 404);

        $path = (string) ($uploadedFile['stored_path'] ?? '');
        $disk = Storage::disk('secure_local');
        abort_if($path === '' || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $this->audit->record('template.downloaded', subject: $template, actor: $user, after: [
            'template_id' => $template->getKey(),
            'filename' => $uploadedFile['original_name'] ?? null,
        ]);

        $filename = (string) ($uploadedFile['original_name'] ?? Str::slug($template->title).'.docx');
        $mime = (string) ($uploadedFile['mime_type'] ?? 'application/octet-stream');

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$this->downloadFilename($filename).'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('update', $template);
        abort_if($template->status === Template::STATUS_DRAFT, 404);

        $user = $this->viewer($request);
        $before = $template->only(['category', 'title', 'body', 'status', 'version', 'structure']);
        $validated = $this->validated($request);
        $structure = is_array($template->structure) ? $template->structure : [];
        $uploadStructure = $this->uploadedFileStructure($request);

        $template->forceFill([
            ...$this->templateAttributes($validated),
            'structure' => $uploadStructure === [] ? $structure : [
                ...$structure,
                ...$uploadStructure,
            ],
            'version' => $template->version + 1,
        ])->save();

        $this->audit->record('template.updated', subject: $template, actor: $user, before: $before, after: [
            'category' => $template->category,
            'title' => $template->title,
            'status' => $template->status,
            'version' => $template->version,
            'has_upload' => $this->uploadedFile($template) !== null,
        ]);

        return to_route('advisor.templates.show', $template)->with('status', 'template-updated');
    }

    /**
     * @param  Builder<Template>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $needle = '%'.Str::lower($search).'%';
        $query->where(function (Builder $query) use ($needle): void {
            $query
                ->whereRaw('lower(title) like ?', [$needle])
                ->orWhereRaw('lower(category) like ?', [$needle])
                ->orWhereRaw('lower(body) like ?', [$needle]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSummary(Template $template): array
    {
        return [
            'id' => $template->id,
            'category' => $template->category,
            'category_label' => Template::categoryLabel($template->category),
            'title' => $template->title,
            'body_excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $template->body) ?? (string) $template->body, 220),
            'status' => $template->status,
            'version' => $template->version,
            'source_reference' => $template->source_reference,
            'uploaded_file' => $this->uploadedFile($template),
            'download_url' => $this->uploadedFile($template) === null
                ? null
                : route('advisor.templates.download', $template, absolute: false),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'show_url' => route('advisor.templates.show', $template, absolute: false),
            'update_url' => route('advisor.templates.update', $template, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateDetail(Template $template): array
    {
        return [
            ...$this->templateSummary($template),
            'body' => $template->body,
            'structure' => $template->structure,
            'creator_name' => $template->creator?->name,
            'created_at' => $template->created_at?->toIso8601String(),
            'learning_update_implementation_id' => $template->learning_update_implementation_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required', 'string', Rule::in(Template::categories())],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:40000'],
            'status' => ['required', 'string', Rule::in(Template::libraryStatuses())],
            'file' => ['nullable', 'file', 'mimes:doc,docx,dot,dotx,pdf', 'max:20480'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $body = trim((string) $request->input('body', ''));

            if ($body === '' && ! $request->file('file') instanceof UploadedFile) {
                $validator->errors()->add('body', 'Provide template body text or upload a template file.');
            }
        });

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function templateAttributes(array $validated): array
    {
        return [
            'category' => $validated['category'],
            'title' => trim((string) $validated['title']),
            'body' => trim((string) ($validated['body'] ?? '')),
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadedFileStructure(Request $request): array
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return [];
        }

        // Security baseline (spec §4): every uploaded file is virus-scanned
        // before persistence. Only clean files reach the encrypted secure_local
        // disk; infected or unscannable uploads are rejected.
        $this->assertCleanUpload($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedPath = sprintf('templates/%s/%s.%s', (string) Str::uuid(), (string) Str::uuid(), $extension);
        $contents = $file->get();
        $written = Storage::disk('secure_local')->put($storedPath, $contents);

        abort_unless($written === true, 500, 'Template file could not be stored.');

        return [
            'source_kind' => 'uploaded_file',
            'uploaded_file' => [
                'stored_path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'extension' => $extension,
                'byte_size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'uploaded_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function assertCleanUpload(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        abort_unless(
            is_string($realPath) && is_file($realPath),
            422,
            'Uploaded file could not be read for malware scanning.',
        );

        $stream = fopen($realPath, 'rb');
        abort_unless(is_resource($stream), 422, 'Uploaded file could not be opened for malware scanning.');

        try {
            $result = $this->scanner->scan($stream);
        } finally {
            fclose($stream);
        }

        abort_if($result->isInfected(), 422, 'Upload rejected because malware was detected.');
        abort_if($result->isError(), 422, 'Upload could not be virus-scanned. Please try again or contact support.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadedFile(Template $template): ?array
    {
        $uploadedFile = data_get($template->structure, 'uploaded_file');

        return is_array($uploadedFile) ? $uploadedFile : null;
    }

    private function downloadFilename(string $filename): string
    {
        return str_replace(['\\', '/', '"', "\r", "\n"], '-', $filename);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
