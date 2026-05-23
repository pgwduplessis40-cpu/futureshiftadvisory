<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BulkCommunication;
use App\Models\BulkCommunicationRecipient;
use App\Models\Client;
use App\Models\User;
use Illuminate\Mail\Mailable;

final class BulkCommunicationMail extends Mailable
{
    public function __construct(
        public readonly BulkCommunication $communication,
        public readonly BulkCommunicationRecipient $recipient,
        public readonly Client $client,
        public readonly User $sender,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->communication->subject)
            ->replyTo((string) $this->sender->email, $this->sender->name)
            ->html($this->bodyHtml());
    }

    private function bodyHtml(): string
    {
        $template = BulkCommunication::templates()[(string) $this->communication->template_key]
            ?? 'Future Shift Advisory update';
        $trackingPixel = $this->recipient->open_token === null
            ? ''
            : sprintf(
                '<img src="%s" width="1" height="1" alt="" style="display:none;border:0;" />',
                e(route('communications.open', $this->recipient->open_token)),
            );

        return sprintf(
            '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2933;">
                <div style="border-bottom:3px solid #2f6f68;padding-bottom:12px;margin-bottom:20px;">
                    <div style="font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#2f6f68;">%s</div>
                    <h1 style="font-size:22px;margin:6px 0 0;">%s</h1>
                </div>
                <p style="font-size:13px;color:#52606d;">For %s</p>
                <div style="font-size:15px;">%s</div>
                <hr style="border:none;border-top:1px solid #d9e2ec;margin:24px 0;" />
                <p style="font-size:12px;color:#627d98;">Sent by %s through Future Shift Advisory.</p>
                %s
            </div>',
            e($template),
            e($this->communication->title),
            e($this->client->legal_name),
            nl2br(e($this->communication->body)),
            e($this->sender->name),
            $trackingPixel,
        );
    }
}
