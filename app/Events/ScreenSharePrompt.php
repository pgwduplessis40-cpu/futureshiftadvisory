<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ScreenSharePrompt implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $connectionId,
        private readonly string $sessionId,
        private readonly string $nonce,
        private readonly string $advisorName,
        private readonly string $expiresAt,
        private readonly array $context,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('screen-share.connection.'.$this->connectionId);
    }

    public function broadcastAs(): string
    {
        return 'screen-share.prompt';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'nonce' => $this->nonce,
            'advisor_name' => $this->advisorName,
            'expires_at' => $this->expiresAt,
            'context' => $this->context,
        ];
    }
}
