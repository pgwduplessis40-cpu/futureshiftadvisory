<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
use Illuminate\Mail\Mailable;

final class ClientEmailFromApp extends Mailable
{
    public function __construct(
        public readonly Client $client,
        public readonly User $sender,
        public readonly string $subjectLine,
        public readonly string $bodyText,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->replyTo((string) $this->sender->email, $this->sender->name)
            ->html($this->bodyHtml());
    }

    private function bodyHtml(): string
    {
        return sprintf(
            '<p>%s</p><hr><p><small>Sent by %s through Future Shift Advisory for %s.</small></p>',
            nl2br(e($this->bodyText)),
            e($this->sender->name),
            e($this->client->legal_name),
        );
    }
}
