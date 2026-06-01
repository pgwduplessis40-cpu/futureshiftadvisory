<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class ReferenceDataStaleNotification extends ChannelAwareNotification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $task
     */
    public function __construct(public readonly array $task) {}

    public function databaseType(): string
    {
        return 'reference_data.stale';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reference data update due')
            ->line($this->message())
            ->action('Open reference data', route('admin.reference-data.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reference data update due',
            'message' => $this->message(),
            'url' => route('admin.reference-data.index', absolute: false),
            'dataset_key' => (string) $this->task['key'],
            'dataset' => (string) $this->task['dataset'],
            'indicator' => $this->task['indicator'] ?? null,
            'status' => (string) $this->task['status'],
            'last_as_at' => $this->task['last_as_at'] ?? null,
            'due_at' => $this->task['due_at'] ?? null,
        ];
    }

    private function message(): string
    {
        $label = (string) $this->task['label'];
        $status = (string) $this->task['status'];
        $dueAt = $this->task['due_at'] ?? null;
        $lastAsAt = $this->task['last_as_at'] ?? null;

        if ($status === 'missing') {
            return "{$label} has no implemented reference-data value yet.";
        }

        return "{$label} is due for review".(is_string($dueAt) ? " by {$dueAt}" : '').(is_string($lastAsAt) ? "; last implemented value is {$lastAsAt}." : '.');
    }
}
