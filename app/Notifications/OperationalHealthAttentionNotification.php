<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class OperationalHealthAttentionNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(
        public readonly OperationalHealthCheckRun $run,
        public readonly OperationalHealthCheckResult $result,
    ) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'operational_health.attention';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Urgent: app health needs attention')
            ->line($this->result->name.' is still '.$this->result->status.' after '.((int) $this->result->consecutive_failures).' consecutive checks.')
            ->line($this->result->issue_summary ?? 'No issue summary was recorded.')
            ->line('Environment: '.$this->run->environment.'. Release: '.($this->run->release_version ?: 'unknown').'.')
            ->action('Open app health', route('admin.app-health.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'App health needs attention',
            'message' => $this->result->issue_summary ?? ($this->result->name.' needs attention.'),
            'url' => route('admin.app-health.index', absolute: false),
            'operational_health_run_id' => $this->run->id,
            'operational_health_result_id' => $this->result->id,
            'check_key' => $this->result->check_key,
            'status' => $this->result->status,
            'fingerprint' => $this->result->fingerprint,
            'consecutive_failures' => $this->result->consecutive_failures,
            'failures_last_7_days' => $this->result->failures_last_7_days,
            'release_version' => $this->run->release_version,
            'environment' => $this->run->environment,
        ];
    }
}
