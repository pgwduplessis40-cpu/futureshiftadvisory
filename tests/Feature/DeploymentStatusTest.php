<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DeploymentStatusTest extends TestCase
{
    private string $path;

    private ?string $originalContents = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('app/deployment.json');
        $this->originalContents = File::exists($this->path) ? File::get($this->path) : null;
        File::ensureDirectoryExists(dirname($this->path));
    }

    protected function tearDown(): void
    {
        if ($this->originalContents === null) {
            File::delete($this->path);
        } else {
            File::put($this->path, $this->originalContents);
        }

        parent::tearDown();
    }

    public function test_it_reports_a_verified_deployment_without_cache_headers(): void
    {
        File::put($this->path, json_encode([
            'version' => '1.2.3',
            'commit' => str_repeat('a', 40),
            'deployed_at' => '2026-07-30T00:00:00Z',
            'client_manifest_sha256' => str_repeat('b', 64),
            'ssr_manifest_sha256' => str_repeat('c', 64),
        ], JSON_THROW_ON_ERROR));

        $response = $this->getJson('/api/deployment');

        $response
            ->assertOk()
            ->assertHeader('X-FSA-Deployment-Status', 'verified')
            ->assertHeader('X-FSA-Release', '1.2.3')
            ->assertJson([
                'status' => 'verified',
                'version' => '1.2.3',
                'commit' => str_repeat('a', 40),
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
    }

    public function test_it_returns_service_unavailable_until_a_release_is_verified(): void
    {
        File::delete($this->path);

        $this->getJson('/api/deployment')
            ->assertStatus(503)
            ->assertHeader('X-FSA-Deployment-Status', 'unverified')
            ->assertJson([
                'status' => 'unverified',
                'version' => null,
                'commit' => null,
            ]);
    }
}
