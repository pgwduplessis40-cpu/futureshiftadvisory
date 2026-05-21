<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final readonly class ChannelDecision
{
    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public array $channels,
        public string $urgency,
        public string $preferenceChannel,
        public string $frequency,
        public bool $mailNow,
        public bool $emailDeferred,
        public bool $platformNow,
        public bool $bypassedPreference,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'channels' => $this->channels,
            'urgency' => $this->urgency,
            'preference_channel' => $this->preferenceChannel,
            'frequency' => $this->frequency,
            'mail_now' => $this->mailNow,
            'email_deferred' => $this->emailDeferred,
            'platform_now' => $this->platformNow,
            'bypassed_preference' => $this->bypassedPreference,
            'decided_at' => now()->toIso8601String(),
        ];
    }
}
