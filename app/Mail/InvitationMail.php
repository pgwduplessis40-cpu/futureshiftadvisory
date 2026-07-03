<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

final class InvitationMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?string $replyToEmail = null,
        public readonly ?string $replyToName = null,
    ) {}

    public function build(): self
    {
        $mail = $this
            ->subject($this->subjectLine)
            ->html($this->bodyHtml());

        if (is_string($this->replyToEmail) && filter_var($this->replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($this->replyToEmail, $this->replyToName ?? $this->replyToEmail);
        }

        return $mail;
    }

    private function bodyHtml(): string
    {
        return sprintf(
            '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2933;">%s</div>',
            nl2br(e($this->bodyText)),
        );
    }
}
