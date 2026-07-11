<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Board\InspirationBoard;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class ReleaseDueInspirationRotations extends Command
{
    protected $signature = 'inspiration:release-due-rotations';

    protected $description = 'Feature quotes whose rotation schedule time has arrived.';

    public function handle(InspirationBoard $board, RequestContext $context): int
    {
        $context->apply('system', []);

        $featured = $board->releaseDueRotations();

        $this->info($featured.' rotation quote'.($featured === 1 ? '' : 's').' featured.');

        return self::SUCCESS;
    }
}
