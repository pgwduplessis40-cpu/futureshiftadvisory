<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Board\InspirationBoard;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class SelectWeeklyInspirationQuote extends Command
{
    protected $signature = 'inspiration:select-weekly-quote';

    protected $description = 'Feature a random published quote when no rotation schedule is active.';

    public function handle(InspirationBoard $board, RequestContext $context): int
    {
        $context->apply('system', []);

        $post = $board->selectWeeklyFallbackQuote();

        $this->info($post === null
            ? 'No weekly fallback quote selected.'
            : 'Featured weekly fallback quote: '.($post->title ?? $post->id));

        return self::SUCCESS;
    }
}
