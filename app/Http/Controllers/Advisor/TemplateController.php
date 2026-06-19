<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\SecureFileWriter;
use App\Services\Templates\TemplateActivationService;
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
        private readonly SecureFileWriter $files,
        private readonly TemplateActivationService $templateActivation,
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
            'reportTypes' => $this->reportTypeOptions(),
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

        try {
            $uploadStructure = $this->uploadedFileStructure($request, $user);
        } catch (InfectedFileException) {
            return back()->withErrors(['file' => 'Upload rejected because malware was detected.']);
        }

        /** @var Template $template */
        $template = Template::query()->create([
            ...$this->templateAttributes($validated),
            'structure' => [
                'source_kind' => 'manual',
                'sections' => [],
                ...$this->templateStructure($validated),
                ...$uploadStructure,
            ],
            'source_reference' => 'manual:user:'.$user->getKey(),
            'version' => 1,
            'created_by_user_id' => $user->getKey(),
            'learning_update_implementation_id' => null,
        ]);

        $this->templateActivation->archiveOverlappingActiveReportTemplates($template);

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
            'reportTypes' => $this->reportTypeOptions(),
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
        $disposition = $this->downloadDisposition($request, $mime);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$this->downloadFilename($filename).'"',
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

        try {
            $uploadStructure = $this->uploadedFileStructure($request, $user);
        } catch (InfectedFileException) {
            return back()->withErrors(['file' => 'Upload rejected because malware was detected.']);
        }

        $template->forceFill([
            ...$this->templateAttributes($validated),
            'structure' => [
                ...$structure,
                ...$this->templateStructure($validated),
                ...$uploadStructure,
            ],
            'version' => $template->version + 1,
        ])->save();

        $this->templateActivation->archiveOverlappingActiveReportTemplates($template);

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
        $uploadedFile = $this->uploadedFile($template);

        return [
            'id' => $template->id,
            'category' => $template->category,
            'category_label' => Template::categoryLabel($template->category),
            'title' => $template->title,
            'body_excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $template->body) ?? (string) $template->body, 220),
            'status' => $template->status,
            'version' => $template->version,
            'source_reference' => $template->source_reference,
            'uploaded_file' => $uploadedFile === null
                ? null
                : [
                    ...$uploadedFile,
                    'can_preview' => $this->canPreviewUploadedFile($uploadedFile),
                ],
            'report_type' => data_get($template->structure, 'report_type'),
            'layout' => data_get($template->structure, 'layout', []),
            'download_url' => $uploadedFile === null
                ? null
                : route('advisor.templates.download', $template, absolute: false),
            'view_url' => $uploadedFile !== null && $this->canPreviewUploadedFile($uploadedFile)
                ? route('advisor.templates.download', [
                    'template' => $template,
                    'disposition' => 'inline',
                ], false)
                : null,
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
            'report_type' => ['nullable', 'string', Rule::in($this->reportTypeValues())],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function templateStructure(array $validated): array
    {
        if (($validated['category'] ?? null) !== Template::CATEGORY_REPORT) {
            return [];
        }

        $structure = [];
        $reportType = trim((string) ($validated['report_type'] ?? ''));
        $accentColor = trim((string) ($validated['accent_color'] ?? ''));

        if ($reportType !== '') {
            $structure['report_type'] = $reportType;
        }

        if ($accentColor !== '') {
            $structure['layout'] = [
                'accent_color' => $accentColor,
            ];
        }

        return $structure;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function reportTypeOptions(): array
    {
        return collect($this->reportTypeValues())
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => ReportType::from($type)->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function reportTypeValues(): array
    {
        return [
            ReportType::Client->value,
            ReportType::Advisor->value,
            ReportType::Stakeholder->value,
            ReportType::Trajectory->value,
        ];
    }

    /**
     * Persist an uploaded template file via SecureFileWriter - the sanctioned
     * upload path for scanning, encrypted storage, provenance, and audit.
     *
     * @return array<string, mixed>
     */
    private function uploadedFileStructure(Request $request, User $user): array
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return [];
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $document = $this->files->write($file, $user, Document::CATEGORY_TEMPLATE_FILE);

        abort_unless(
            $document->scanner_result === Document::SCANNER_CLEAN,
            422,
            'Upload could not be virus-scanned. Please try again or contact support.',
        );

        return [
            'source_kind' => 'uploaded_file',
            'uploaded_file' => [
                'document_id' => (string) $document->getKey(),
                'stored_path' => $document->stored_path,
                'original_name' => $document->original_filename,
                'mime_type' => $document->mime_type ?: 'application/octet-stream',
                'extension' => $extension,
                'byte_size' => $document->byte_size,
                'sha256' => $document->sha256,
                'uploaded_at' => now()->toIso8601String(),
            ],
        ];
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

    /**
     * @param  array<string, mixed>  $uploadedFile
     */
    private function canPreviewUploadedFile(array $uploadedFile): bool
    {
        return strtolower((string) ($uploadedFile['mime_type'] ?? '')) === 'application/pdf';
    }

    private function downloadDisposition(Request $request, string $mime): string
    {
        if ($request->query('disposition') !== 'inline') {
            return 'attachment';
        }

        return strtolower($mime) === 'application/pdf' ? 'inline' : 'attachment';
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
