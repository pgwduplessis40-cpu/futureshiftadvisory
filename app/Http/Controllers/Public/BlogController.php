<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Blog\BlogPosts;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(BlogPosts $blog): Response
    {
        return Inertia::render('public/blog', [
            'posts' => $blog->published()
                ->map(fn ($post): array => $blog->summary($post))
                ->all(),
        ]);
    }

    public function show(string $slug, BlogPosts $blog): Response
    {
        $post = $blog->findPublished($slug);

        abort_if($post === null, 404);

        return Inertia::render('public/blog-post', [
            'post' => $blog->publicPayload($post),
        ]);
    }
}
