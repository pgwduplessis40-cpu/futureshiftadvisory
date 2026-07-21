<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ScreenShareSignal implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly string $connectionId,
        private readonly string $sessionId,
        private readonly string $fromConnectionId,
        private readonly int $signalId,
        private readonly string $signalType,
        private readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('screen-share.connection.'.$this->connectionId);
    }

    public function broadcastAs(): string
    {
        return 'screen-share.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->signalId,
            'session_id' => $this->sessionId,
            'from_connection_id' => $this->fromConnectionId,
            'type' => $this->signalType,
            'payload' => $this->payload,
        ];
    }
}
