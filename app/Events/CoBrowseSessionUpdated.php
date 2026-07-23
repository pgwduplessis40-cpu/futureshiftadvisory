<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CoBrowseSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CoBrowseSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        private readonly string $connectionId,
        private readonly CoBrowseSession $session,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('co-browse.connection.'.$this->connectionId);
    }

    public function broadcastAs(): string
    {
        return 'co-browse.session-updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'session_id' => (string) $this->session->getKey(),
            'status' => $this->session->status,
            'end_reason' => $this->session->end_reason,
            'expires_at' => $this->session->expires_at?->toIso8601String(),
        ];
    }
}
