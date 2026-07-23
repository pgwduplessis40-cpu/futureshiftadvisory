<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CoBrowseAction;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CoBrowseActionDispatched implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        private readonly string $connectionId,
        private readonly CoBrowseAction $action,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('co-browse.connection.'.$this->connectionId);
    }

    public function broadcastAs(): string
    {
        return 'co-browse.action';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->action->getKey(),
            'session_id' => (string) $this->action->session_id,
            'type' => $this->action->type,
            'payload' => $this->action->payload,
        ];
    }
}
