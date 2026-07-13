<?php

namespace Tests\Unit\Support;

use App\Support\ReleaseVersion;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReleaseVersionTest extends TestCase
{
    private string $versionFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionFile = storage_path('framework/testing-release-version');
    }

    protected function tearDown(): void
    {
        @unlink($this->versionFile);

        parent::tearDown();
    }

    public function test_it_reads_the_tracked_release_version_at_runtime(): void
    {
        file_put_contents($this->versionFile, "2.4.6\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_override', null);
        Config::set('app.legacy_release_version', '1.0.0');

        $this->assertSame('2.4.6', app(ReleaseVersion::class)->current());
    }

    public function test_an_explicit_environment_override_takes_precedence(): void
    {
        file_put_contents($this->versionFile, "2.4.6\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_override', '3.0.0-rc.1');

        $this->assertSame('3.0.0-rc.1', app(ReleaseVersion::class)->current());
    }

    public function test_it_falls_back_to_the_legacy_version_when_the_file_is_invalid(): void
    {
        file_put_contents($this->versionFile, "next\n");
        Config::set('app.release_version_file', $this->versionFile);
        Config::set('app.release_version_override', null);
        Config::set('app.legacy_release_version', '1.9.2');

        $this->assertSame('1.9.2', app(ReleaseVersion::class)->current());
    }
}
