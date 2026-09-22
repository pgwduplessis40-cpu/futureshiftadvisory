<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ContactCaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_submission_when_the_turnstile_check_fails(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $this->from(route('public.contact'))
            ->post(route('public.contact.store'), [
                'name' => 'Bot Sender',
                'email' => 'bot@example.test',
                'message' => 'A long enough but automated enquiry message.',
                'cf-turnstile-response' => 'invalid-token',
            ])
            ->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors('captcha');

        $this->assertDatabaseCount('prospect_leads', 0);
    }

    public function test_it_accepts_a_submission_when_the_turnstile_check_passes(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->post(route('public.contact.store'), [
            'name' => 'Real Person',
            'email' => 'real@example.test',
            'message' => 'We would like a clear review of our business priorities.',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect(route('public.contact.thanks'));

        $this->assertDatabaseHas('prospect_leads', ['email' => 'real@example.test']);
    }

    public function test_it_skips_verification_when_no_secret_is_configured(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => null]);

        $this->post(route('public.contact.store'), [
            'name' => 'Real Person',
            'email' => 'nokey@example.test',
            'message' => 'We would like a clear review of our business priorities.',
        ])->assertRedirect(route('public.contact.thanks'));

        $this->assertDatabaseHas('prospect_leads', ['email' => 'nokey@example.test']);
    }
}
