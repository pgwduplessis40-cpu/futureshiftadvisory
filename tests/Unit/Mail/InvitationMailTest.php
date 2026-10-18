<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\InvitationMail;
use Tests\TestCase;

final class InvitationMailTest extends TestCase
{
    public function test_login_link_is_clickable_while_body_markup_remains_escaped(): void
    {
        $mail = new InvitationMail(
            subjectLine: 'Future Shift Advisory invitation',
            bodyText: "<script>alert('example')</script>\n".route('login'),
        );

        $html = $mail->render();

        $this->assertStringContainsString(
            '<a href="'.e(route('login')).'">Log in on the website</a>',
            $html,
        );
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
