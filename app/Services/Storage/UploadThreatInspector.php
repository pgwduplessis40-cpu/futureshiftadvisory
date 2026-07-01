<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Integration\VirusScanner\ScanResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

final class UploadThreatInspector
{
    private const BLOCKED_EXTENSIONS = [
        'app',
        'bat',
        'cmd',
        'com',
        'cpl',
        'deb',
        'dll',
        'dmg',
        'docm',
        'dotm',
        'exe',
        'gadget',
        'hta',
        'htm',
        'html',
        'iso',
        'jar',
        'js',
        'jse',
        'mjs',
        'msi',
        'php',
        'phtml',
        'phar',
        'potm',
        'ppam',
        'pptm',
        'ps1',
        'reg',
        'scr',
        'sh',
        'svg',
        'vb',
        'vbe',
        'vbs',
        'xlam',
        'xlsm',
        'xltm',
    ];

    private const EMBEDDED_EXECUTABLE_EXTENSIONS = [
        '.bat',
        '.cmd',
        '.com',
        '.dll',
        '.exe',
        '.hta',
        '.jar',
        '.js',
        '.msi',
        '.ps1',
        '.scr',
        '.sh',
        '.vbs',
    ];

    public function inspect(UploadedFile $uploadedFile, string $path): ?ScanResult
    {
        $extension = Str::lower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: '');

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return $this->threat('disallowed-active-extension', [
                'extension' => $extension,
            ]);
        }

        $prefix = $this->prefix($path);
        if ($prefix === '') {
            return null;
        }

        if ($this->hasExecutableMagic($prefix)) {
            return $this->threat('executable-file-signature', [
                'extension' => $extension,
            ]);
        }

        if ($this->looksLikeScriptOrHtml($prefix)) {
            return $this->threat('script-or-html-content', [
                'extension' => $extension,
            ]);
        }

        if ($this->looksLikeActivePdf($prefix)) {
            return $this->threat('active-pdf-content', [
                'extension' => $extension,
            ]);
        }

        if ($this->isZipContainer($prefix)) {
            return $this->inspectZipContainer($path, $extension);
        }

        return null;
    }

    private function prefix(string $path): string
    {
        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            return '';
        }

        try {
            $bytes = fread($handle, 131072);

            return is_string($bytes) ? $bytes : '';
        } finally {
            fclose($handle);
        }
    }

    private function hasExecutableMagic(string $bytes): bool
    {
        return str_starts_with($bytes, 'MZ')
            || str_starts_with($bytes, "\x7fELF")
            || str_starts_with($bytes, "\xfe\xed\xfa")
            || str_starts_with($bytes, "\xce\xfa\xed\xfe")
            || str_starts_with($bytes, "\xcf\xfa\xed\xfe")
            || str_starts_with($bytes, "\xca\xfe\xba\xbe");
    }

    private function looksLikeScriptOrHtml(string $bytes): bool
    {
        $normalised = strtolower(ltrim($bytes));

        return str_starts_with($normalised, '<!doctype html')
            || str_starts_with($normalised, '<html')
            || str_starts_with($normalised, '<script')
            || str_starts_with($normalised, '<?php')
            || str_starts_with($normalised, '<svg')
            || str_starts_with($normalised, '#!')
            || str_contains($normalised, '<script')
            || str_contains($normalised, 'javascript:');
    }

    private function looksLikeActivePdf(string $bytes): bool
    {
        $normalised = strtolower($bytes);

        if (! str_starts_with($normalised, '%pdf-') && ! str_contains($normalised, '%pdf-')) {
            return false;
        }

        return str_contains($normalised, '/javascript')
            || str_contains($normalised, '/js ')
            || str_contains($normalised, '/openaction')
            || str_contains($normalised, '/launch')
            || str_contains($normalised, '/embeddedfile');
    }

    private function isZipContainer(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "PK\x05\x06")
            || str_starts_with($bytes, "PK\x07\x08");
    }

    private function inspectZipContainer(string $path, string $extension): ?ScanResult
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($path);
        if ($opened !== true) {
            return null;
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name)) {
                    continue;
                }

                $normalisedName = strtolower(str_replace('\\', '/', $name));

                if (str_ends_with($normalisedName, 'vbaproject.bin')) {
                    return $this->threat('office-macro-project', [
                        'extension' => $extension,
                        'entry' => $normalisedName,
                    ]);
                }

                if (str_contains($normalisedName, '/activex/')) {
                    return $this->threat('office-activex-control', [
                        'extension' => $extension,
                        'entry' => $normalisedName,
                    ]);
                }

                if ($this->hasEmbeddedExecutableName($normalisedName)) {
                    return $this->threat('embedded-executable-in-archive', [
                        'extension' => $extension,
                        'entry' => $normalisedName,
                    ]);
                }

                if (str_ends_with($normalisedName, '.rels') && $this->hasExternalOfficeRelationship($zip, $index)) {
                    return $this->threat('external-office-relationship', [
                        'extension' => $extension,
                        'entry' => $normalisedName,
                    ]);
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    private function hasEmbeddedExecutableName(string $entry): bool
    {
        foreach (self::EMBEDDED_EXECUTABLE_EXTENSIONS as $extension) {
            if (str_ends_with($entry, $extension)) {
                return true;
            }
        }

        return false;
    }

    private function hasExternalOfficeRelationship(ZipArchive $zip, int $index): bool
    {
        $contents = $zip->getFromIndex($index, 262144);
        if (! is_string($contents)) {
            return false;
        }

        $normalised = strtolower($contents);

        return str_contains($normalised, 'targetmode="external"')
            && (
                str_contains($normalised, 'target="http://')
                || str_contains($normalised, 'target="https://')
                || str_contains($normalised, 'target="file://')
                || str_contains($normalised, 'target="\\\\')
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function threat(string $signature, array $payload = []): ScanResult
    {
        return ScanResult::infected($signature, [
            'engine' => 'upload-threat-inspector',
            ...$payload,
        ]);
    }
}
