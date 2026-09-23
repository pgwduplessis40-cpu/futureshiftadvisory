<?php

declare(strict_types=1);

namespace App\Services\Blog;

use Illuminate\Support\Str;

final class BlogMarkdownRenderer
{
    public function render(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
