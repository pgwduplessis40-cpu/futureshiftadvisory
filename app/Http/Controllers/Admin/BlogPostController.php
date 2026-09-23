<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\BlogMarkdownImporter;
use App\Services\Blog\BlogPostManager;
use App\Services\Blog\BlogPosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        ]);
    }

    public function publish(Request $request, BlogPost $blogPost): RedirectResponse
    {
        Gate::authorize('publish', $blogPost);

        $this->manager->publish($blogPost, $this->actor($request));

        return to_route('admin.blog.edit', $blogPost)->with('status', 'blog-post-published');
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
}
