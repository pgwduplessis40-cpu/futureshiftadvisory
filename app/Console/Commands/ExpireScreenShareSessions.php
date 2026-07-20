<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScreenShare\ScreenShareSessions;
use Illuminate\Console\Command;

final class ExpireScreenShareSessions extends Command
{
    protected $signature = 'screen-share:expire';

    protected $description = 'End expired or stale client screen-support sessions.';

    public function handle(ScreenShareSessions $sessions): int
    {
        $expired = $sessions->expireDueSessions();

        $this->info("{$expired->count()} screen-support session".($expired->count() === 1 ? '' : 's').' expired.');

        return self::SUCCESS;
    }
}
