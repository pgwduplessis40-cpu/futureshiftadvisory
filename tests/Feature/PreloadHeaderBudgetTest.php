<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

final class PreloadHeaderBudgetTest extends TestCase
{
    public function test_asset_heavy_pages_keep_headers_within_the_gateway_budget_and_load_every_asset(): void
    {
        $manifest = ['resources/js/fixture.tsx' => [
            'src' => 'resources/js/fixture.tsx',
            'file' => 'assets/fixture-entry.js',
            'isEntry' => true,
            'imports' => [],
        ]];
        for ($index = 0; $index < 40; $index++) {
            $key = '_history-part-'.$index.'.js';
            $manifest['resources/js/fixture.tsx']['imports'][] = $key;
            $manifest[$key] = ['file' => 'assets/entrepreneur-assessment-history-part-'.$index.'-abcdefgh.js'];
        }
        $vite = Mockery::mock(Vite::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $vite->shouldReceive('manifest')->andReturn($manifest);
        $vite->shouldReceive('isRunningHot')->andReturn(false);
        $this->instance(Vite::class, $vite);
        Route::middleware('web')->get('/_testing/preload-header-budget', fn () => response(
            (string) app(Vite::class)(['resources/js/fixture.tsx']),
        ));

        $response = $this->get('https://futureshiftadvisory.nz/_testing/preload-header-budget')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Link');

        // Include status-line headroom and session/security headers, not just
        // Link. Linux nginx commonly has a 4 KB FastCGI response-header buffer.
        self::assertLessThan(4096, strlen((string) $response->headers) + 32);
        self::assertNotEmpty($response->headers->getCookies());
        self::assertStringContainsString('fixture-entry.js', (string) $response->headers->get('Link'));
        self::assertCount(41, $vite->preloadedAssets());
        foreach ($manifest as $chunk) {
            $response->assertSee($chunk['file'], escape: false);
        }
    }
}
