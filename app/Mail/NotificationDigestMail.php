<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

final class NotificationDigestMail extends Mailable
{
    /**
     * @param  array<int, array{id:string, type:string, data:array<string, mixed>, created_at:string}>  $items
     */
    public function __construct(
        public readonly User $user,
        public readonly string $frequency,
        public readonly array $items,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Future Shift Advisory '.$this->frequency.' notification digest')
            ->html($this->bodyHtml());
    }

    private function bodyHtml(): string
    {
        $items = collect($this->items)
            ->map(fn (array $item): string => sprintf(
                '<li><strong>%s</strong><br><span>%s</span><br><small>%s</small></li>',
                e($item['data']['title'] ?? class_basename($item['type'])),
                e($item['data']['message'] ?? $item['data']['summary'] ?? 'A notification is waiting in Future Shift Advisory.'),
                e($item['created_at']),
            ))
            ->implode('');

        return sprintf(
            '<h1>%s notification digest</h1><p>Hello %s, these notifications are ready for review.</p><ul>%s</ul>',
            e(ucfirst($this->frequency)),
            e($this->user->name),
            $items,
        );
    }
}
