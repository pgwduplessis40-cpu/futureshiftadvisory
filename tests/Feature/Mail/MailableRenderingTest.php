<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\EngagementType;
use App\Mail\BulkCommunicationMail;
use App\Mail\ClientEmailFromApp;
use App\Mail\InvitationMail;
use App\Mail\NotificationDigestMail;
use App\Mail\ProspectLeadReceived;
use App\Models\BulkCommunication;
use App\Models\BulkCommunicationRecipient;
use App\Models\Client;
use App\Models\ProspectLead;
use App\Models\User;
use Tests\TestCase;

final class MailableRenderingTest extends TestCase
{
    public function test_bulk_communication_renders_an_escaped_branded_email_with_open_tracking(): void
    {
        $mail = new BulkCommunicationMail(
            new BulkCommunication([
                'template_key' => BulkCommunication::TEMPLATE_ACTION_REQUIRED,
                'subject' => 'A <time-sensitive> update',
                'title' => 'Action <required>',
                'body' => "Please review <this>.\nThank you.",
            ]),
            new BulkCommunicationRecipient(['open_token' => 'tracking-token']),
            $this->client('Client <Name>'),
            $this->sender(),
        );

        $mail->build();

        $mail->assertHasSubject('A <time-sensitive> update');
        $mail->assertHasReplyTo('advisor@example.test', 'Advisor Name');

        $html = $mail->render();

        $this->assertStringContainsString('Action required', $html);
        $this->assertStringContainsString('Action &lt;required&gt;', $html);
        $this->assertStringContainsString('Client &lt;Name&gt;', $html);
        $this->assertStringContainsString('Please review &lt;this&gt;.<br', $html);
        $this->assertStringContainsString(route('communications.open', 'tracking-token'), $html);
    }

    public function test_bulk_communication_omits_tracking_when_the_recipient_has_no_token(): void
    {
        $mail = new BulkCommunicationMail(
            new BulkCommunication([
                'template_key' => 'unknown-template',
                'subject' => 'General update',
                'title' => 'Update',
                'body' => 'The body.',
            ]),
            new BulkCommunicationRecipient,
            $this->client('Client name'),
            $this->sender(),
        );

        $html = $mail->render();

        $this->assertStringContainsString('Future Shift Advisory update', $html);
        $this->assertStringNotContainsString('width="1" height="1"', $html);
    }

    public function test_client_and_invitation_emails_escape_content_and_only_accept_valid_reply_to_addresses(): void
    {
        $clientEmail = new ClientEmailFromApp(
            $this->client('Client <Name>'),
            $this->sender(),
            'Client update',
            "Keep <this> private.\nThanks.",
        );
        $validInvitation = new InvitationMail(
            'Welcome',
            "Open <the link>.\nThanks.",
            'reply@example.test',
            'Reply contact',
        );
        $invalidInvitation = new InvitationMail('Welcome', 'Body', 'not-an-email');

        $clientEmail->build();
        $validInvitation->build();
        $invalidInvitation->build();

        $clientEmail->assertHasSubject('Client update');
        $clientEmail->assertHasReplyTo('advisor@example.test', 'Advisor Name');
        $this->assertStringContainsString('Keep &lt;this&gt; private.<br', $clientEmail->render());
        $this->assertStringContainsString('Client &lt;Name&gt;', $clientEmail->render());
        $validInvitation->assertHasReplyTo('reply@example.test', 'Reply contact');
        $this->assertStringContainsString('Open &lt;the link&gt;.<br', $validInvitation->render());
        $this->assertSame([], $invalidInvitation->replyTo);
    }

    public function test_notification_digest_uses_safe_fallbacks_for_incomplete_notification_payloads(): void
    {
        $mail = new NotificationDigestMail($this->sender(), 'weekly', [
            [
                'id' => 'one',
                'type' => 'App\\Notifications\\AssessmentReady',
                'data' => ['title' => 'Review <ready>', 'message' => 'See <details>.'],
                'created_at' => '2026-08-24T09:00:00+12:00',
            ],
            [
                'id' => 'two',
                'type' => 'App\\Notifications\\FallbackNotice',
                'data' => ['summary' => 'A <summary>.'],
                'created_at' => '2026-08-24T10:00:00+12:00',
            ],
            [
                'id' => 'three',
                'type' => 'App\\Notifications\\MissingPayload',
                'data' => [],
                'created_at' => '2026-08-24T11:00:00+12:00',
            ],
        ]);

        $mail->build();

        $mail->assertHasSubject('Future Shift Advisory weekly notification digest');
        $html = $mail->render();

        $this->assertStringContainsString('Weekly notification digest', $html);
        $this->assertStringContainsString('Review &lt;ready&gt;', $html);
        $this->assertStringContainsString('See &lt;details&gt;.', $html);
        $this->assertStringContainsString('A &lt;summary&gt;.', $html);
        $this->assertStringContainsString('MissingPayload', $html);
        $this->assertStringContainsString('A notification is waiting in Future Shift Advisory.', $html);
    }

    public function test_prospect_lead_mail_preserves_the_reply_to_address_and_view_data(): void
    {
        $lead = new ProspectLead([
            'name' => 'Prospect Name',
            'email' => 'prospect@example.test',
        ]);
        $mail = new ProspectLeadReceived($lead);

        $envelope = $mail->envelope();
        $content = $mail->content();

        $this->assertSame('New enquiry — Prospect Name', $envelope->subject);
        $this->assertSame('prospect@example.test', $envelope->replyTo[0]->address);
        $this->assertSame('emails.prospect-lead-received', $content->view);
        $this->assertSame($lead, $content->with['lead']);
    }

    private function sender(): User
    {
        return new User([
            'name' => 'Advisor Name',
            'email' => 'advisor@example.test',
        ]);
    }

    private function client(string $legalName): Client
    {
        return new Client([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => $legalName,
        ]);
    }
}
