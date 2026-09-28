<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\Blog\BlogPostManager;
use App\Services\Blog\BlogPosts;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply(User::TYPE_SUPER_ADMIN, []);
    }

    public function test_the_legacy_post_is_migrated_and_publicly_available(): void
    {
        $this->get('/blog/how-to-start-a-business-in-new-zealand')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog-post')
                ->where('post.slug', 'how-to-start-a-business-in-new-zealand')
                ->where('post.date', '22 September 2026'));
    }

    public function test_public_surfaces_only_expose_published_snapshots(): void
    {
        $published = $this->publishedPost('published-post', 'Published title', 'Published body');
        $this->draftPost('draft-post', 'Draft title', 'Draft body');

        $this->get(route('public.blog'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog')
                ->where('posts.0.slug', $published->slug));

        $this->get('/blog/'.$published->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog-post')
                ->where('post.title', 'Published title'));

        $this->get('/blog/draft-post')->assertNotFound();

        $this->get(route('public.sitemap'))
            ->assertOk()
            ->assertSee('/blog/published-post', false)
            ->assertDontSee('/blog/draft-post', false);

        $this->get(route('public.llms'))
            ->assertOk()
            ->assertSee('Published title')
            ->assertDontSee('Draft title');
    }

    public function test_markdown_is_rendered_with_raw_html_escaped(): void
    {
        $post = $this->publishedPost(
            'safe-markdown',
            'Safe Markdown',
            "## Heading\n\n<script>alert('xss')</script>\n\n[unsafe](javascript:alert('xss'))",
        );

        $html = app(BlogPosts::class)->publicPayload($post)['html'];

        $this->assertStringContainsString('<h2>Heading</h2>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_admin_preview_returns_to_the_blog_editor(): void
    {
        $admin = $this->superAdmin();
        $post = $this->draftPost('preview-post', 'Preview title', 'Preview body');

        $this->actingAsMfa($admin)
            ->get(route('admin.blog.preview', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/blog-post')
                ->where('post.title', 'Preview title')
                ->where('preview.backUrl', route('admin.blog.edit', $post, absolute: false)));
    }

    public function test_super_admin_can_draft_publish_revise_and_unpublish_without_changing_the_original_publication_date(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.store'), [
                'title' => 'First working title',
                'slug' => 'first-working-title',
                'description' => 'First working description',
                'body' => 'First working body',
            ])
            ->assertRedirect();

        $post = BlogPost::query()->where('slug', 'first-working-title')->firstOrFail();
        $this->assertSame(BlogPost::STATUS_DRAFT, $post->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'blog_post.created',
            'subject_id' => $post->id,
        ]);

        $this->actingAsMfa($admin)->post(route('admin.blog.publish', $post))->assertRedirect();
        $post->refresh();
        $firstPublishedAt = $post->published_at;

        $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
        $this->assertSame('First working title', $post->published_title);
        $this->assertNotNull($firstPublishedAt);

        $this->actingAsMfa($admin)
            ->patch(route('admin.blog.update', $post), [
                'title' => 'Revised working title',
                'slug' => 'first-working-title',
                'description' => 'Revised working description',
                'body' => 'Revised working body',
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame('Revised working title', $post->title);
        $this->assertSame('First working title', $post->published_title);

        $this->get('/blog/first-working-title')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('post.title', 'First working title'));

        $this->actingAsMfa($admin)->post(route('admin.blog.publish', $post))->assertRedirect();
        $post->refresh();
        $this->assertSame('Revised working title', $post->published_title);
        $this->assertTrue($post->published_at?->equalTo($firstPublishedAt) ?? false);

        $this->actingAsMfa($admin)
            ->patch(route('admin.blog.update', $post), ['slug' => 'changed-slug'])
            ->assertSessionHasErrors('slug');

        $this->actingAsMfa($admin)->post(route('admin.blog.unpublish', $post))->assertRedirect();
        $this->get('/blog/first-working-title')->assertNotFound();

        $this->actingAsMfa($admin)->post(route('admin.blog.publish', $post))->assertRedirect();
        $this->assertTrue($post->refresh()->published_at?->equalTo($firstPublishedAt) ?? false);
    }

    public function test_drafts_can_be_incomplete_but_publishing_requires_all_public_fields(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.store'), ['title' => 'Incomplete'])
            ->assertRedirect();

        $post = BlogPost::query()->where('title', 'Incomplete')->firstOrFail();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.publish', $post))
            ->assertSessionHasErrors(['description', 'body']);
    }

    public function test_publishing_saves_the_current_editor_content_before_creating_the_public_snapshot(): void
    {
        $admin = $this->superAdmin();
        $post = $this->draftPost('working-draft', 'Working draft', 'Original body');

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.publish', $post), [
                'title' => 'Published from the editor',
                'slug' => 'published-from-the-editor',
                'description' => 'Current editor description',
                'body' => 'Current editor body',
            ])
            ->assertRedirect(route('admin.blog.index'))
            ->assertSessionHas('toast', [
                'type' => 'success',
                'message' => 'Published “Published from the editor”. It is now live.',
            ]);

        $post->refresh();

        $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
        $this->assertSame('Published from the editor', $post->title);
        $this->assertSame('Current editor body', $post->body);
        $this->assertSame('Published from the editor', $post->published_title);
        $this->assertSame('Current editor description', $post->published_description);
        $this->assertSame('Current editor body', $post->published_body);
    }

    public function test_import_prefills_a_draft_without_persisting_it_and_rejects_malformed_input(): void
    {
        $admin = $this->superAdmin();
        $before = BlogPost::query()->count();
        $file = UploadedFile::fake()->createWithContent('imported-article.md', "---\ntitle: Imported title\ndescription: Imported description\ndate: 2026-09-23\n---\nImported body");

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.import'), ['file' => $file])
            ->assertRedirect(route('admin.blog.create'));

        $this->assertSame($before, BlogPost::query()->count());

        $this->get(route('admin.blog.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('import.title', 'Imported title')
                ->where('import.slug', 'imported-article')
                ->where('import.body', 'Imported body'));

        $bad = UploadedFile::fake()->createWithContent('bad.md', 'not valid frontmatter');
        $this->actingAsMfa($admin)
            ->post(route('admin.blog.import'), ['file' => $bad])
            ->assertSessionHasErrors('file');

        $this->assertSame($before, BlogPost::query()->count());
    }

    public function test_super_admin_can_batch_review_imports_and_schedule_each_complete_snapshot(): void
    {
        $admin = $this->superAdmin();
        $first = UploadedFile::fake()->createWithContent('first-article.md', "---\ntitle: First imported title\ndescription: First imported description\ndate: 2026-09-23\n---\nFirst imported body");
        $second = UploadedFile::fake()->createWithContent('second-article.md', "---\ntitle: Second imported title\ndescription: Second imported description\ndate: 2026-09-24\n---\nSecond imported body");
        $scheduledAt = now('Pacific/Auckland')->addDay()->startOfMinute();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.batch-import.preview'), ['files' => [$first, $second]])
            ->assertRedirect(route('admin.blog.batch-import'));

        $this->assertDatabaseMissing('blog_posts', ['slug' => 'first-article']);

        $this->get(route('admin.blog.batch-import'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/blog/BatchImport')
                ->where('imports.0.filename', 'first-article.md')
                ->where('imports.1.slug', 'second-article'));

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.batch-import.store'), [
                'scheduled_at' => [
                    $scheduledAt->format('Y-m-d\\TH:i'),
                    '',
                ],
            ])
            ->assertRedirect(route('admin.blog.index'));

        $scheduled = BlogPost::query()->where('slug', 'first-article')->firstOrFail();
        $draft = BlogPost::query()->where('slug', 'second-article')->firstOrFail();

        $this->assertSame(BlogPost::STATUS_SCHEDULED, $scheduled->status);
        $this->assertSame('First imported title', $scheduled->scheduled_title);
        $this->assertTrue($scheduled->scheduled_at?->equalTo($scheduledAt) ?? false);
        $this->assertSame(BlogPost::STATUS_DRAFT, $draft->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'blog_post.scheduled',
            'subject_id' => $scheduled->id,
        ]);
    }

    public function test_scheduled_posts_stay_private_until_the_due_command_publishes_the_approved_snapshot(): void
    {
        $admin = $this->superAdmin();
        $post = $this->draftPost('scheduled-post', 'Scheduled title', 'Scheduled body');
        $scheduledAt = now('Pacific/Auckland')->addDay()->startOfMinute();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.schedule', $post), [
                'title' => 'Scheduled title',
                'slug' => 'scheduled-post',
                'description' => 'Scheduled description',
                'body' => 'Scheduled body',
                'scheduled_at' => $scheduledAt->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect(route('admin.blog.edit', $post));

        $post->refresh();
        $this->assertSame(BlogPost::STATUS_SCHEDULED, $post->status);
        $this->get('/blog/scheduled-post')->assertNotFound();
        $this->get(route('public.sitemap'))->assertDontSee('/blog/scheduled-post', false);

        app(RequestContext::class)->apply('system', []);

        $this->assertSame(0, app(BlogPostManager::class)->publishDue($scheduledAt->copy()->subMinute()));
        $this->assertSame(1, app(BlogPostManager::class)->publishDue($scheduledAt));

        $post->refresh();
        $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
        $this->assertSame('Scheduled title', $post->published_title);
        $this->assertNull($post->scheduled_at);
        $this->assertTrue($post->published_at?->equalTo($scheduledAt) ?? false);
        $this->get('/blog/scheduled-post')->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'blog_post.scheduled_published',
            'subject_id' => $post->id,
        ]);
    }

    public function test_scheduled_posts_cannot_be_published_manually_before_their_due_time(): void
    {
        $admin = $this->superAdmin();
        $post = $this->draftPost('manual-publish-guard', 'Manual publish guard', 'Scheduled body');
        $scheduledAt = now('Pacific/Auckland')->addDay()->startOfMinute();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.schedule', $post), [
                'title' => 'Manual publish guard',
                'slug' => 'manual-publish-guard',
                'description' => 'Scheduled description',
                'body' => 'Scheduled body',
                'scheduled_at' => $scheduledAt->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.publish', $post))
            ->assertSessionHasErrors([
                'post' => 'This post is scheduled for automatic publication. Update or cancel the schedule before publishing it.',
            ]);

        $post->refresh();
        $this->assertSame(BlogPost::STATUS_SCHEDULED, $post->status);
        $this->assertTrue($post->scheduled_at?->equalTo($scheduledAt) ?? false);
        $this->get('/blog/manual-publish-guard')->assertNotFound();
    }

    public function test_working_edits_do_not_change_a_scheduled_snapshot_without_an_explicit_update_or_cancellation(): void
    {
        $admin = $this->superAdmin();
        $post = $this->draftPost('scheduled-snapshot', 'Original title', 'Original body');
        $scheduledAt = now('Pacific/Auckland')->addDay()->startOfMinute();

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.schedule', $post), [
                'title' => 'Original title',
                'slug' => 'scheduled-snapshot',
                'description' => 'Original description',
                'body' => 'Original body',
                'scheduled_at' => $scheduledAt->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect();

        $this->actingAsMfa($admin)
            ->patch(route('admin.blog.update', $post), [
                'title' => 'Changed working title',
                'slug' => 'scheduled-snapshot',
                'description' => 'Changed working description',
                'body' => 'Changed working body',
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame('Changed working title', $post->title);
        $this->assertSame('Original title', $post->scheduled_title);

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.schedule', $post), [
                'title' => 'Changed working title',
                'slug' => 'scheduled-snapshot',
                'description' => 'Changed working description',
                'body' => 'Changed working body',
                'scheduled_at' => $scheduledAt->copy()->addDay()->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame('Changed working title', $post->scheduled_title);

        $this->actingAsMfa($admin)
            ->post(route('admin.blog.cancel-schedule', $post))
            ->assertRedirect(route('admin.blog.edit', $post));

        $post->refresh();
        $this->assertSame(BlogPost::STATUS_DRAFT, $post->status);
        $this->assertNull($post->scheduled_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'blog_post.schedule_cancelled',
            'subject_id' => $post->id,
        ]);
    }

    public function test_a_failed_audit_write_rolls_back_the_blog_write(): void
    {
        $admin = $this->superAdmin();
        $this->installRejectBlogAuditTrigger();

        try {
            $this->actingAsMfa($admin)
                ->post(route('admin.blog.store'), [
                    'title' => 'Will roll back',
                    'slug' => 'will-roll-back',
                    'description' => 'Description',
                    'body' => 'Body',
                ])
                ->assertServerError();

            $this->assertDatabaseMissing('blog_posts', ['slug' => 'will-roll-back']);
        } finally {
            $this->removeRejectBlogAuditTrigger();
        }
    }

    public function test_non_super_admin_cannot_manage_blog_posts(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)->get(route('admin.blog.index'))->assertForbidden();
        $this->actingAsMfa($advisor)
            ->post(route('admin.blog.store'), ['title' => 'Blocked'])
            ->assertForbidden();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        return $admin;
    }

    private function draftPost(string $slug, string $title, string $body): BlogPost
    {
        return BlogPost::query()->create([
            'slug' => $slug,
            'title' => $title,
            'description' => $title.' description',
            'body' => $body,
            'status' => BlogPost::STATUS_DRAFT,
        ]);
    }

    private function publishedPost(string $slug, string $title, string $body): BlogPost
    {
        $publishedAt = CarbonImmutable::parse('2026-10-01 12:00:00', 'Pacific/Auckland')->utc();

        return BlogPost::query()->create([
            'slug' => $slug,
            'title' => $title,
            'description' => $title.' description',
            'body' => $body,
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_title' => $title,
            'published_description' => $title.' description',
            'published_body' => $body,
            'published_at' => $publishedAt,
            'published_revision_at' => $publishedAt,
        ]);
    }

    private function installRejectBlogAuditTrigger(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION reject_blog_audit() RETURNS trigger AS $$
                BEGIN
                    IF NEW.action = 'blog_post.created' THEN
                        RAISE EXCEPTION 'audit blocked';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER reject_blog_audit
                BEFORE INSERT ON audit_events
                FOR EACH ROW EXECUTE FUNCTION reject_blog_audit();
            SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_blog_audit
            BEFORE INSERT ON audit_events
            WHEN NEW.action = 'blog_post.created'
            BEGIN
                SELECT RAISE(ABORT, 'audit blocked');
            END;
        SQL);
    }

    private function removeRejectBlogAuditTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reject_blog_audit ON audit_events');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS reject_blog_audit()');
        }
    }
}
