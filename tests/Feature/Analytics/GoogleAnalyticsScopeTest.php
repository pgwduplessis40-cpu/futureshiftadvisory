<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use Tests\TestCase;

/**
 * The public site and the authenticated app share one Inertia shell, so a
 * globally injected analytics tag also runs inside the portal - where page
 * titles embed client and person names and would be sent to a third party as
 * `page_title`. Analytics must therefore load on public marketing pages only.
 */
final class GoogleAnalyticsScopeTest extends TestCase
{
    private const MEASUREMENT_ID = 'G-TESTONLY123';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google_analytics.measurement_id', self::MEASUREMENT_ID);
    }

    public function test_public_pages_load_the_analytics_tag(): void
    {
        foreach (['/', '/services', '/about', '/faq', '/contact'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee(self::MEASUREMENT_ID, false);
        }
    }

    public function test_authenticated_app_pages_do_not_load_the_analytics_tag(): void
    {
        // Guest-facing auth screens share the shell but are not public pages.
        $this->get('/login')
            ->assertOk()
            ->assertDontSee(self::MEASUREMENT_ID, false)
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_no_analytics_tag_is_rendered_when_no_measurement_id_is_configured(): void
    {
        config()->set('services.google_analytics.measurement_id', null);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }
}
