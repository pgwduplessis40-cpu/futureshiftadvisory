<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Middleware\RedirectWwwToApex;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * No database: the redirect short-circuits ahead of session and auth
 * middleware, so a www request must never need a connection at all.
 */
final class RedirectWwwToApexTest extends TestCase
{
    public function test_www_requests_are_permanently_redirected_to_the_apex_host(): void
    {
        $this->get('http://www.futureshiftadvisory.nz/login')
            ->assertStatus(301)
            ->assertRedirect('http://futureshiftadvisory.nz/login');
    }

    public function test_the_redirect_preserves_the_path_and_query_string(): void
    {
        $this->get('http://www.futureshiftadvisory.nz/login?foo=bar')
            ->assertStatus(301)
            ->assertRedirect('http://futureshiftadvisory.nz/login?foo=bar');
    }

    public function test_the_apex_host_passes_through_untouched(): void
    {
        $reached = false;

        $response = (new RedirectWwwToApex)->handle(
            Request::create('https://futureshiftadvisory.nz/login', 'GET'),
            function () use (&$reached): Response {
                $reached = true;

                return new Response('passed through');
            },
        );

        $this->assertTrue($reached);
        $this->assertSame('passed through', $response->getContent());
    }
}
