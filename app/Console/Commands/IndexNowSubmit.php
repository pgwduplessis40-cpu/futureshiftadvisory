<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\Blog\BlogPosts;
use App\Services\Seo\IndexNowSubmitter;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class IndexNowSubmit extends Command
{
    protected $signature = 'fsa:indexnow-submit {--all : Submit every published blog URL, not just recently published or revised ones}';

    protected $description = 'Notify IndexNow (Bing, Yandex, et al.) of newly published or revised blog posts.';

    public function handle(BlogPosts $posts, IndexNowSubmitter $indexNow, RequestContext $context): int
    {
        $context->apply('system', []);

        if (! $indexNow->isConfigured()) {
            $this->info('IndexNow key is not configured; nothing was submitted.');

            return self::SUCCESS;
        }

        $base = rtrim((string) config('app.url'), '/');
        $urls = $this->option('all')
            ? $this->allUrls($posts, $base)
            : $this->recentUrls($base);

        if ($urls === []) {
            $this->info('No blog URLs to submit to IndexNow.');

            return self::SUCCESS;
        }

        $accepted = $indexNow->submit($urls);

        $this->info($accepted
            ? 'Submitted '.count($urls).' URL(s) to IndexNow.'
            : 'IndexNow did not accept the submission; see the integration health dashboard.');

        // A degraded external service must never fail the scheduler. The
        // resilience layer already records the outcome for the health dashboard.
        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function allUrls(BlogPosts $posts, string $base): array
    {
        $urls = $posts->published()
            ->map(fn (BlogPost $post): string => $base.'/blog/'.$post->slug)
            ->all();

        if ($urls === []) {
            return [];
        }

        return [$base.'/blog', ...$urls];
    }

    /**
     * @return list<string>
     */
    private function recentUrls(string $base): array
    {
        $since = now()->subMinutes(65);

        $slugs = BlogPost::query()
            ->publiclyPublished()
            ->where(fn ($query) => $query
                ->where('published_at', '>=', $since)
                ->orWhere('published_revision_at', '>=', $since))
            ->orderByDesc('published_at')
            ->pluck('slug')
            ->all();

        if ($slugs === []) {
            return [];
        }

        return [
            $base.'/blog',
            ...array_map(fn (string $slug): string => $base.'/blog/'.$slug, $slugs),
        ];
    }
}
