<?php

namespace App\Support;

use JsonException;

final class ReleaseVersion
{
    public function current(): string
    {
        $override = trim((string) config('app.release_version_override'));

        if ($this->isValid($override)) {
            return $override;
        }

        $deploymentVersion = $this->fromDeploymentMetadataFile(
            config('app.release_version_deployment_metadata_file'),
        );

        if ($this->isValid($deploymentVersion)) {
            return $deploymentVersion;
        }

        $configuredFile = config('app.release_version_file');
        $versionFile = is_string($configuredFile) && trim($configuredFile) !== ''
            ? $configuredFile
            : base_path('VERSION');
        $fromFile = $this->fromFile($versionFile);

        if ($this->isValid($fromFile)) {
            return $fromFile;
        }

        $legacy = trim((string) config(
            'app.legacy_release_version',
            config('app.release_version', '1.0.0'),
        ));

        return $this->isValid($legacy) ? $legacy : '1.0.0';
    }

    private function fromFile(string $path): string
    {
        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? trim($contents) : '';
    }

    private function fromDeploymentMetadataFile(mixed $path): string
    {
        if (! is_string($path) || trim($path) === '' || ! is_readable($path)) {
            return '';
        }

        try {
            $metadata = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (! is_array($metadata)) {
            return '';
        }

        foreach (['version', 'commit', 'deployed_at', 'client_manifest_sha256', 'ssr_manifest_sha256'] as $key) {
            if (! is_string($metadata[$key] ?? null) || trim($metadata[$key]) === '') {
                return '';
            }
        }

        return trim($metadata['version']);
    }

    private function isValid(string $version): bool
    {
        return preg_match(
            '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
            $version,
        ) === 1;
    }
}
