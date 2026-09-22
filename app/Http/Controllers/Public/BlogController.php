<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Public\BlogRepository;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(BlogRepository $blog): Response
    {
        return Inertia::render('public/blog', [
            'posts' => $blog->all(),
        ]);
    }

    public function show(string $slug, BlogRepository $blog): Response
    {
        $post = $blog->find($slug);

        abort_if($post === null, 404);

        return Inertia::render('public/blog-post', [
            'post' => $post,
        ]);
    }
}
