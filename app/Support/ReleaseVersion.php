<?php

namespace App\Support;

final class ReleaseVersion
{
    public function current(): string
    {
        $override = trim((string) config('app.release_version_override'));

        if ($this->isValid($override)) {
            return $override;
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

    private function isValid(string $version): bool
    {
        return preg_match(
            '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
            $version,
        ) === 1;
    }
}
