<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use App\Services\Seo\IndexNowSubmitter;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IndexNowSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        config([
            'app.url' => 'https://futureshiftadvisory.nz',
            'services.indexnow.key' => 'b4e575664eb3417a487ec0340d6dae5b',
            'services.indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
        ]);
    }

    public function test_the_submitter_posts_changed_urls_with_the_key_payload(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $accepted = app(IndexNowSubmitter::class)->submit([
            'https://futureshiftadvisory.nz/blog/how-to-start-a-business-in-new-zealand',
        ]);

        $this->assertTrue($accepted);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.indexnow.org/indexnow'
                && $data['host'] === 'futureshiftadvisory.nz'
                && $data['key'] === 'b4e575664eb3417a487ec0340d6dae5b'
                && $data['keyLocation'] === 'https://futureshiftadvisory.nz/b4e575664eb3417a487ec0340d6dae5b.txt'
                && $data['urlList'] === ['https://futureshiftadvisory.nz/blog/how-to-start-a-business-in-new-zealand'];
        });
    }

    public function test_the_all_option_submits_every_published_blog_url(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        $this->publishedPost('all-published-insight');

        Artisan::call('fsa:indexnow-submit', ['--all' => true]);

        Http::assertSent(function (Request $request): bool {
            $urls = $request->data()['urlList'];

            return in_array('https://futureshiftadvisory.nz/blog', $urls, true)
                && in_array('https://futureshiftadvisory.nz/blog/all-published-insight', $urls, true);
        });
    }

    public function test_the_default_run_submits_only_recently_published_posts(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        $this->publishedPost('fresh-insight', now());
        $this->publishedPost('stale-insight', now()->subDays(30));

        Artisan::call('fsa:indexnow-submit');

        Http::assertSent(function (Request $request): bool {
            $urls = $request->data()['urlList'];

            return in_array('https://futureshiftadvisory.nz/blog/fresh-insight', $urls, true)
                && ! in_array('https://futureshiftadvisory.nz/blog/stale-insight', $urls, true);
        });
    }

    public function test_nothing_is_sent_when_no_key_is_configured(): void
    {
        config(['services.indexnow.key' => '']);
        Http::fake();

        $accepted = app(IndexNowSubmitter::class)->submit([
            'https://futureshiftadvisory.nz/blog/anything',
        ]);

        $this->assertFalse($accepted);
        Http::assertNothingSent();
    }

    private function publishedPost(string $slug, ?CarbonInterface $publishedAt = null): BlogPost
    {
        $publishedAt ??= CarbonImmutable::parse('2026-10-01 12:00:00', 'Pacific/Auckland')->utc();
        $title = ucfirst(str_replace('-', ' ', $slug));

        return BlogPost::query()->create([
            'slug' => $slug,
            'title' => $title,
            'description' => $slug.' description',
            'body' => $slug.' body',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_title' => $title,
            'published_description' => $slug.' description',
            'published_body' => $slug.' body',
            'published_at' => $publishedAt,
            'published_revision_at' => $publishedAt,
        ]);
    }
}
