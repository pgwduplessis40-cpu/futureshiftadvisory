<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LearningUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class BiasMonitorSignalNotification extends ChannelAwareNotification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $signal
     */
    public function __construct(
        public readonly LearningUpdate $candidate,
        public readonly array $signal,
    ) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'ai.bias.systematic_signal';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Urgent: systematic AI bias signal detected')
            ->line('The bias monitor detected a systematic severity skew across analysis outputs.')
            ->line('Module: '.$this->string('module'))
            ->line('Cohort: '.$this->string('dimension_label').' = '.$this->string('value'))
            ->line(sprintf(
                'High-severity rate: %s vs baseline %s.',
                $this->percent('cohort_high_rate'),
                $this->percent('baseline_high_rate'),
            ))
            ->line('A governed learning candidate has been queued for human review. No correction has been applied automatically.')
            ->action('Open notifications', route('notifications.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Systematic AI bias signal',
            'message' => sprintf(
                '%s analyses show a high-severity skew for %s = %s.',
                $this->string('module'),
                $this->string('dimension_label'),
                $this->string('value'),
            ),
            'url' => route('notifications.index', absolute: false),
            'urgency' => $this->urgency(),
            'learning_update_id' => $this->candidate->id,
            'signal_key' => $this->string('signal_key'),
            'module' => $this->string('module'),
            'dimension' => $this->string('dimension'),
            'value' => $this->string('value'),
            'metric' => $this->string('metric'),
            'cohort_count' => $this->signal['cohort_count'] ?? null,
            'baseline_count' => $this->signal['baseline_count'] ?? null,
            'cohort_high_rate' => $this->signal['cohort_high_rate'] ?? null,
            'baseline_high_rate' => $this->signal['baseline_high_rate'] ?? null,
            'rate_delta' => $this->signal['rate_delta'] ?? null,
            'recorded_at' => now()->toIso8601String(),
        ];
    }

    private function string(string $key): string
    {
        return is_scalar($this->signal[$key] ?? null) ? (string) $this->signal[$key] : 'unknown';
    }

    private function percent(string $key): string
    {
        $value = $this->signal[$key] ?? null;

        if (! is_numeric($value)) {
            return 'unknown';
        }

        return round(((float) $value) * 100, 1).'%';
    }
}
