<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DocumentVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class DocumentDiscrepancyUrgentNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly DocumentVerification $verification) {}

    public function urgency(): string
    {
        return 'urgent';
    }

    public function databaseType(): string
    {
        return 'document.verification.discrepancy';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verification = $this->verification->loadMissing('document.client');
        $document = $verification->document;

        return (new MailMessage)
            ->subject('Urgent: document verification discrepancy')
            ->line('A supporting document appears to conflict with an attached client claim.')
            ->line('Client: '.($document?->client?->legal_name ?? 'Unknown client'))
            ->line('Document: '.($document?->original_filename ?? $verification->document_id))
            ->line('Claim: '.$verification->claim_text)
            ->line('Review the advisor dashboard before releasing related analysis.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $verification = $this->verification->loadMissing('document.client');

        return [
            'urgency' => $this->urgency(),
            'document_verification_id' => $verification->id,
            'document_id' => $verification->document_id,
            'client_id' => $verification->client_id,
            'client_name' => $verification->document?->client?->legal_name,
            'original_filename' => $verification->document?->original_filename,
            'outcome' => $verification->outcome,
            'claim_text' => $verification->claim_text,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
