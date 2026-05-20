<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ProspectLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectLeadReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProspectLead $lead)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry — '.$this->lead->name,
            replyTo: [$this->lead->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prospect-lead-received',
            with: ['lead' => $this->lead],
        );
    }
}
