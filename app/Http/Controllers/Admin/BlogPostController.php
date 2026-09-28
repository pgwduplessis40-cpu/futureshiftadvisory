<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\BlogMarkdownImporter;
use App\Services\Blog\BlogPostManager;
use App\Services\Blog\BlogPosts;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogPostManager $manager,
        private readonly BlogMarkdownImporter $importer,
        private readonly BlogPosts $posts,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', BlogPost::class);

        return Inertia::render('admin/blog/Index', [
            'posts' => BlogPost::query()
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post): array => $this->posts->adminSummary($post))
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', BlogPost::class);

        $import = $request->session()->pull('blog_import');

        return Inertia::render('admin/blog/Edit', [
            'post' => null,
            'import' => is_array($import) ? $import : null,
        ]);
    }

    public function batchImport(Request $request): Response
    {
        Gate::authorize('create', BlogPost::class);

        $imports = $request->session()->get('blog_batch_imports', []);

        return Inertia::render('admin/blog/BatchImport', [
            'imports' => is_array($imports) ? $imports : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', BlogPost::class);

        $post = $this->manager->create($this->validatedWorkingAttributes($request), $this->actor($request));

        return to_route('admin.blog.edit', $post)->with('status', 'blog-post-created');
    }

    public function edit(BlogPost $blogPost): Response
    {
        Gate::authorize('update', $blogPost);

        return Inertia::render('admin/blog/Edit', [
            'post' => $this->posts->editorPayload($blogPost),
            'import' => null,
        ]);
    }

    public function update(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('update', $blogPost);

        $this->manager->saveWorking($blogPost, $this->validatedWorkingAttributes($request, $blogPost), $this->actor($request));

        return to_route('admin.blog.edit', $blogPost)->with('status', 'blog-post-saved');
    }

    public function preview(BlogPost $blogPost): Response
    {
        Gate::authorize('view', $blogPost);

        return Inertia::render('public/blog-post', [
            'post' => $this->posts->previewPayload($blogPost),
            'preview' => [
                'backUrl' => route('admin.blog.edit', $blogPost, absolute: false),
            ],
        ]);
    }

    public function publish(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('publish', $blogPost);

        $published = $this->manager->publish(
            $blogPost,
            $this->actor($request),
            $this->validatedWorkingAttributes($request, $blogPost),
        );

        return to_route('admin.blog.index')
            ->with('status', 'blog-post-published')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Published “'.$published->title.'”. It is now live.',
            ]);
    }

    public function schedule(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('publish', $blogPost);

        $scheduledAt = $this->validatedScheduledAt($request, 'scheduled_at');
        $this->manager->schedule(
            $blogPost,
            $scheduledAt,
            $this->actor($request),
            $this->validatedWorkingAttributes($request, $blogPost),
        );

        return to_route('admin.blog.edit', $blogPost)
            ->with('status', 'blog-post-scheduled')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Publication scheduled for '.$scheduledAt->setTimezone('Pacific/Auckland')->format('j F Y, g:ia T').'.',
            ]);
    }

    public function cancelSchedule(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('publish', $blogPost);

        $this->manager->cancelSchedule($blogPost, $this->actor($request));

        return to_route('admin.blog.edit', $blogPost)
            ->with('status', 'blog-post-schedule-cancelled')
            ->with('toast', [
                'type' => 'info',
                'message' => 'Publication schedule cancelled. This post is a draft again.',
            ]);
    }

    public function unpublish(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('unpublish', $blogPost);

        $this->manager->unpublish($blogPost, $this->actor($request));

        return to_route('admin.blog.edit', $blogPost)->with('status', 'blog-post-unpublished');
    }

    public function destroy(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('delete', $blogPost);

        $this->manager->delete($blogPost, $this->actor($request));

        return to_route('admin.blog.index')->with('status', 'blog-post-deleted');
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', BlogPost::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:512', 'extensions:md'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $import = $this->importer->parse($file);

        return to_route('admin.blog.create')->with('blog_import', $import);
    }

    public function previewBatchImport(Request $request): RedirectResponse
    {
        Gate::authorize('create', BlogPost::class);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:512', 'extensions:md'],
        ]);

        $imports = [];
        foreach ($validated['files'] as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            try {
                $import = $this->importer->parse($file);
            } catch (ValidationException $exception) {
                $messages = collect($exception->errors())->flatten();

                throw ValidationException::withMessages([
                    'files.'.$index => (string) $messages->first(),
                ]);
            }

            $imports[] = [
                'filename' => $file->getClientOriginalName(),
                ...$import,
            ];
        }

        $this->ensureBatchSlugsAreAvailable($imports);

        $request->session()->put('blog_batch_imports', $imports);

        return to_route('admin.blog.batch-import');
    }

    public function storeBatchImport(Request $request): RedirectResponse
    {
        Gate::authorize('create', BlogPost::class);

        $imports = $request->session()->get('blog_batch_imports');
        if (! is_array($imports) || $imports === []) {
            throw ValidationException::withMessages([
                'files' => 'Choose Markdown files to review before creating drafts.',
            ]);
        }

        $this->ensureBatchSlugsAreAvailable($imports);
        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'array'],
            'scheduled_at.*' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);

        $scheduledAt = [];
        foreach ($validated['scheduled_at'] ?? [] as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $scheduledAt[$index] = $this->parseScheduledAt((string) $value, 'scheduled_at.'.$index);
        }

        $posts = $this->manager->createMany($imports, $this->actor($request), $scheduledAt);
        $request->session()->forget('blog_batch_imports');

        return to_route('admin.blog.index')
            ->with('status', 'blog-posts-imported')
            ->with('toast', [
                'type' => 'success',
                'message' => $posts->count().' blog '.($posts->count() === 1 ? 'draft was' : 'drafts were').' created.',
            ]);
    }

    /**
     * @return array{title?:string|null,slug?:string|null,description?:string|null,body?:string|null}
     */
    private function validatedWorkingAttributes(Request $request, ?BlogPost $post = null): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('blog_posts', 'slug')->ignore($post?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:100000'],
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function validatedScheduledAt(Request $request, string $field): CarbonImmutable
    {
        $validated = $request->validate([
            $field => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);

        return $this->parseScheduledAt((string) $validated[$field], $field);
    }

    private function parseScheduledAt(string $value, string $field): CarbonImmutable
    {
        try {
            $scheduledAt = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $value, 'Pacific/Auckland');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => 'Choose a valid New Zealand publication date and time.',
            ]);
        }

        if ($scheduledAt->format('Y-m-d\\TH:i') !== $value) {
            throw ValidationException::withMessages([
                $field => 'Choose a valid New Zealand publication date and time.',
            ]);
        }

        $scheduledAt = $scheduledAt->utc();
        if ($scheduledAt->lte(now())) {
            throw ValidationException::withMessages([
                $field => 'Choose a future publication date and time.',
            ]);
        }

        return $scheduledAt;
    }

    /**
     * @param  list<array{filename?:string,title:string,slug:string,description:string,body:string}>  $imports
     */
    private function ensureBatchSlugsAreAvailable(array $imports): void
    {
        $seen = [];
        foreach ($imports as $index => $import) {
            $slug = $import['slug'];

            if ($slug === '' || isset($seen[$slug])) {
                throw ValidationException::withMessages([
                    'files.'.$index => 'Each imported post needs a unique URL slug.',
                ]);
            }

            $seen[$slug] = true;
        }

        $existing = BlogPost::query()
            ->whereIn('slug', array_keys($seen))
            ->pluck('slug')
            ->all();

        if ($existing !== []) {
            throw ValidationException::withMessages([
                'files' => 'A blog post already uses this URL: '.implode(', ', $existing).'.',
            ]);
        }
    }
}
