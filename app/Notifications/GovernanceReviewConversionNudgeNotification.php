<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NpoEngagement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class GovernanceReviewConversionNudgeNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly NpoEngagement $engagement,
        public readonly int $nudgeDay,
    ) {}

    public function databaseType(): string
    {
        return 'npo.governance_review_conversion_nudge';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $engagement = $this->engagement->loadMissing('client');

        return (new MailMessage)
            ->subject('Governance Review conversion follow-up due')
            ->line("A {$this->nudgeDay}-day Governance Review conversion follow-up is due.")
            ->line('Client: '.($engagement->client?->legal_name ?? 'Unknown client'))
            ->line('Review the conversion status and decide whether to convert, decline, or continue follow-up.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $engagement = $this->engagement->loadMissing('client');

        return [
            'npo_engagement_id' => $engagement->id,
            'client_id' => $engagement->client_id,
            'client_name' => $engagement->client?->legal_name,
            'title' => 'Governance Review conversion follow-up due',
            'message' => "The {$this->nudgeDay}-day Governance Review conversion follow-up is due.",
            'nudge_day' => $this->nudgeDay,
            'url' => route('advisor.clients.show', $engagement->client_id, absolute: false),
            'report_delivered_at' => $engagement->report_delivered_at?->toIso8601String(),
        ];
    }
}
