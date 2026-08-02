<?php

namespace Tests\Unit\Support;

use App\Support\ReleaseVersion;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReleaseVersionTest extends TestCase
{
    private string $versionFile;

    private string $deploymentMetadataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionFile = storage_path('framework/testing-release-version');
        $this->deploymentMetadataFile = storage_path('framework/testing-release-version-deployment.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->versionFile);
        @unlink($this->deploymentMetadataFile);

        parent::tearDown();
    }

    public function test_it_reads_the_tracked_release_version_at_runtime(): void
    {
        file_put_contents($this->versionFile, "2.4.6\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_deployment_metadata_file', $this->deploymentMetadataFile);
        Config::set('app.release_version_override', null);
        Config::set('app.legacy_release_version', '1.0.0');

        $this->assertSame('2.4.6', app(ReleaseVersion::class)->current());
    }

    public function test_an_explicit_environment_override_takes_precedence(): void
    {
        file_put_contents($this->versionFile, "2.4.6\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_deployment_metadata_file', $this->deploymentMetadataFile);
        Config::set('app.release_version_override', '3.0.0-rc.1');

        $this->assertSame('3.0.0-rc.1', app(ReleaseVersion::class)->current());
    }

    public function test_it_falls_back_to_the_legacy_version_when_the_file_is_invalid(): void
    {
        file_put_contents($this->versionFile, "next\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_deployment_metadata_file', $this->deploymentMetadataFile);
        Config::set('app.release_version_override', null);
        Config::set('app.legacy_release_version', '1.9.2');

        $this->assertSame('1.9.2', app(ReleaseVersion::class)->current());
    }

    public function test_a_verified_deployment_tag_takes_precedence_over_the_source_fallback(): void
    {
        file_put_contents($this->versionFile, "1.0.76\n");
        file_put_contents($this->deploymentMetadataFile, json_encode([
            'version' => '1.0.77',
            'commit' => str_repeat('a', 40),
            'deployed_at' => '2026-08-02T00:00:00Z',
            'client_manifest_sha256' => str_repeat('b', 64),
            'ssr_manifest_sha256' => str_repeat('c', 64),
        ], JSON_THROW_ON_ERROR));
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_deployment_metadata_file', $this->deploymentMetadataFile);
        Config::set('app.release_version_override', null);

        $this->assertSame('1.0.77', app(ReleaseVersion::class)->current());
    }
}
