<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Blog\BlogPostManager;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class PublishDueBlogPosts extends Command
{
    protected $signature = 'blog:publish-due';

    protected $description = 'Publish approved blog snapshots whose scheduled time has arrived.';

    public function handle(BlogPostManager $posts, RequestContext $context): int
    {
        $context->apply('system', []);

        $published = $posts->publishDue();
        $this->info($published.' scheduled blog '.($published === 1 ? 'post' : 'posts').' published.');

        return self::SUCCESS;
    }
}
