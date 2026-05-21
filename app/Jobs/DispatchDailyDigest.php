<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommunicationPreference;
use App\Services\Notifications\DigestDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DispatchDailyDigest implements ShouldQueue
{
    use Queueable;

    public function handle(DigestDispatcher $dispatcher): void
    {
        $dispatcher->dispatch(CommunicationPreference::FREQUENCY_DAILY);
    }
}
