<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Mail\ProspectLeadReceived;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class LlmsTxtTest extends TestCase
{
    public function test_public_llms_document_is_plain_text_and_uses_the_configured_canonical_url(): void
    {
        config()->set('app.public_url', 'https://futureshiftadvisory.nz/');

        $this->get(route('public.llms'))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSeeText('# Future Shift Advisory')
            ->assertSeeText('https://futureshiftadvisory.nz/services#')
            ->assertSeeText('## Frequently asked questions')
            ->assertSeeText('hello@futureshiftadvisory.nz');
    }

    public function test_public_sitemap_uses_the_configured_canonical_url_and_valid_xml_content_type(): void
    {
        config()->set('app.public_url', 'https://futureshiftadvisory.nz/');

        $this->get(route('public.sitemap'))
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<loc>https://futureshiftadvisory.nz/services</loc>', false)
            ->assertSee('<changefreq>monthly</changefreq>', false)
            ->assertSee('<priority>0.9</priority>', false);
    }

    public function test_public_contact_form_persists_the_lead_notifies_the_owner_and_shows_the_thank_you_page(): void
    {
        Mail::fake();
        config()->set('mail.owner_address', 'owner@example.test');

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/contact')
                ->has('engagementOptions', 6));

        $this->post(route('public.contact.store'), [
            'name' => 'Aroha Prospect',
            'email' => 'aroha@example.test',
            'phone' => '021 555 0101',
            'company' => 'Prospect Limited',
            'engagement_interest' => 'standard_advisory',
            'message' => 'We would like a clear review of our current business priorities.',
        ])
            ->assertRedirect(route('public.contact.thanks'));

        $this->assertDatabaseHas('prospect_leads', [
            'name' => 'Aroha Prospect',
            'email' => 'aroha@example.test',
            'engagement_interest' => 'standard_advisory',
            'source' => 'public_contact_form',
        ]);
        Mail::assertSent(ProspectLeadReceived::class, fn (ProspectLeadReceived $mail): bool => $mail->lead->email === 'aroha@example.test');

        $this->get(route('public.contact.thanks'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('public/contact-thanks'));
    }

    public function test_public_contact_form_keeps_the_enquiry_when_owner_email_delivery_fails(): void
    {
        config()->set('mail.owner_address', 'owner@example.test');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail service unavailable.'));

        $this->post(route('public.contact.store'), [
            'name' => 'Resilient Prospect',
            'email' => 'resilient@example.test',
            'message' => 'Please contact me about an evidence-based business review.',
        ])
            ->assertRedirect(route('public.contact.thanks'));

        $this->assertDatabaseHas('prospect_leads', [
            'name' => 'Resilient Prospect',
            'email' => 'resilient@example.test',
            'source' => 'public_contact_form',
        ]);
    }
}
