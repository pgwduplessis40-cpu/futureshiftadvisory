<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CoBrowse\CoBrowseSessions;
use Illuminate\Console\Command;

final class ExpireCoBrowseSessions extends Command
{
    protected $signature = 'co-browse:expire';

    protected $description = 'End expired or stale consent-based guided-assistance sessions.';

    public function handle(CoBrowseSessions $sessions): int
    {
        $expired = $sessions->expireDueSessions();

        $this->info("{$expired->count()} guided-assistance session".($expired->count() === 1 ? '' : 's').' expired.');

        return self::SUCCESS;
    }
}
