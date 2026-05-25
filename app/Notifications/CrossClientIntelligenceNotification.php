<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IndustryIntelligenceSignal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class CrossClientIntelligenceNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly IndustryIntelligenceSignal $signal) {}

    public function databaseType(): string
    {
        return 'intelligence.cross_client_signal';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cross-client industry pattern detected')
            ->line('An anonymised industry-level pattern reached the cohort threshold.')
            ->line('Industry: '.$this->signal->industry_code)
            ->line('Pattern: '.(string) data_get($this->signal->aggregate, 'pattern', 'industry signal'))
            ->line('Only aggregate counts are included. Client names and record-level values are suppressed.')
            ->action('Open notifications', route('notifications.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Cross-client industry pattern',
            'message' => sprintf(
                '%s pattern detected for %s across an anonymised cohort.',
                (string) data_get($this->signal->aggregate, 'pattern', 'Industry'),
                $this->signal->industry_code,
            ),
            'url' => route('notifications.index', absolute: false),
            'industry_code' => $this->signal->industry_code,
            'signal_type' => $this->signal->signal_type,
            'cohort_size' => $this->signal->cohort_size,
            'privacy' => $this->signal->aggregate['privacy'] ?? null,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
