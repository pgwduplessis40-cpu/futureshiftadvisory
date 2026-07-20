<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ScreenShare\ScreenShareSessions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class EndScreenShareSessionIfDisconnected implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $connectionId,
    ) {
        $this->onQueue('realtime');
    }

    public function handle(ScreenShareSessions $sessions): void
    {
        $sessions->endIfConnectionNotReconnected($this->sessionId, $this->connectionId);
    }
}
