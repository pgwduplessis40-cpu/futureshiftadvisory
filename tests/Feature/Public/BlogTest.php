<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Support\Public\BlogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_repository_reads_sorts_and_guards_markdown_posts(): void
    {
        $dir = sys_get_temp_dir().'/fsa-blog-'.uniqid();
        mkdir($dir);
        file_put_contents(
            $dir.'/older-post.md',
            "---\ntitle: Older Post\ndescription: An older one.\ndate: 2026-01-01\n---\n## Heading\n\nBody text.",
        );
        file_put_contents(
            $dir.'/newer-post.md',
            "---\ntitle: Newer Post\ndescription: A newer one.\ndate: 2026-06-01\n---\nHello world.",
        );

        try {
            $repo = new BlogRepository($dir);
            $all = $repo->all();

            $this->assertCount(2, $all);
            $this->assertSame('newer-post', $all[0]['slug']); // newest first
            $this->assertSame('Newer Post', $all[0]['title']);

            $post = $repo->find('newer-post');
            $this->assertNotNull($post);
            $this->assertStringContainsString('Hello world.', $post['html']);

            $this->assertNull($repo->find('does-not-exist'));
            // basename() strips traversal, so this resolves to a non-existent file.
            $this->assertNull($repo->find('../../etc/passwd'));
        } finally {
            array_map('unlink', glob($dir.'/*.md') ?: []);
            rmdir($dir);
        }
    }

    public function test_the_blog_index_and_post_pages_render(): void
    {
        $this->get(route('public.blog'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog')
                ->has('posts'));

        $this->get('/blog/how-to-start-a-business-in-new-zealand')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog-post')
                ->where('post.slug', 'how-to-start-a-business-in-new-zealand'));
    }

    public function test_a_missing_post_returns_404(): void
    {
        $this->get('/blog/no-such-post')->assertNotFound();
    }
}
