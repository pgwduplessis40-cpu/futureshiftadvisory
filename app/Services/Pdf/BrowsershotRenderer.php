<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Closure;
use Spatie\Browsershot\Browsershot;

final class BrowsershotRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        [$html, $footer] = $this->extractPdfFooter($html);
        $timeout = max(1, (int) config('services.browsershot.timeout_seconds', 60));
        $nodeBinary = $this->binaryPath('node_binary', [
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/snap/bin/node',
            'C:\\Program Files\\nodejs\\node.exe',
        ]);
        $npmBinary = $this->binaryPath('npm_binary', [
            '/usr/local/bin/npm',
            '/usr/bin/npm',
            '/snap/bin/npm',
            'C:\\Program Files\\nodejs\\npm.cmd',
        ]);

        $shot = Browsershot::html($html)
            ->format('A4')
            ->margins(18, 16, $footer === null ? 18 : 32, 16)
            ->showBackground()
            ->noSandbox()
            ->timeout($timeout);

        if ($nodeBinary !== null) {
            $shot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary !== null) {
            $shot->setNpmBinary($npmBinary);
        }

        $chromePath = $this->chromePath($nodeBinary);
        if ($chromePath !== null) {
            $shot->setChromePath($chromePath);
        }

        if ($footer !== null) {
            $shot
                ->showBrowserHeaderAndFooter()
                ->hideHeader()
                ->footerHtml($footer);
        }

        return $this->withExecutionTimeLimit($timeout + 10, $shot->pdf(...));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function binaryPath(string $configKey, array $candidates): ?string
    {
        $configured = config('services.browsershot.'.$configKey);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $pathBinary = $this->pathBinary(str_contains($configKey, 'npm') ? 'npm' : 'node');

        if ($pathBinary !== null) {
            return $pathBinary;
        }

        return null;
    }

    private function chromePath(?string $nodeBinary): ?string
    {
        $configured = config('services.browsershot.chrome_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ([
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        foreach (['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome', 'msedge'] as $binary) {
            $path = $this->pathBinary($binary);

            if ($path !== null) {
                return $path;
            }
        }

        return $this->puppeteerChromePath($nodeBinary);
    }

    private function pathBinary(string $binary): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where '.$binary
            : 'command -v '.escapeshellarg($binary);
        $output = [];
        $exitCode = 1;

        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $candidates = [];

        foreach ($output as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '' && is_file($candidate)) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows' && $binary === 'npm') {
            foreach ($candidates as $candidate) {
                if (str_ends_with(strtolower($candidate), '.cmd')) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    private function puppeteerChromePath(?string $nodeBinary): ?string
    {
        if ($nodeBinary === null || ! function_exists('exec')) {
            return null;
        }

        $script = <<<'JS'
const path = require('path');
const root = process.argv[1];
const cacheDirectory = process.argv[2];
if (cacheDirectory) {
    process.env.PUPPETEER_CACHE_DIR = cacheDirectory;
}
const puppeteer = require(path.join(root, 'node_modules', 'puppeteer'));
process.stdout.write(puppeteer.executablePath());
JS;
        $output = [];
        $exitCode = 1;
        $cacheDirectory = $this->puppeteerCacheDirectory();

        @exec(
            escapeshellarg($nodeBinary)
            .' -e '.escapeshellarg($script)
            .' '.escapeshellarg(base_path())
            .' '.escapeshellarg($cacheDirectory),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $path = trim(implode("\n", $output));

        return $path !== '' && is_file($path) ? $path : null;
    }

    private function puppeteerCacheDirectory(): string
    {
        $configured = config('services.browsershot.puppeteer_cache_dir');

        return is_string($configured) && $configured !== ''
            ? $configured
            : storage_path('app/browsershot');
    }

    private function withExecutionTimeLimit(int $seconds, Closure $callback): string
    {
        $previousLimit = ini_get('max_execution_time');

        @set_time_limit($seconds);

        try {
            return $callback();
        } finally {
            if ($previousLimit !== false) {
                @ini_set('max_execution_time', $previousLimit);
            }
        }
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function extractPdfFooter(string $html): array
    {
        if (preg_match('/<template\s+data-pdf-footer[^>]*>(.*?)<\/template>/is', $html, $matches) !== 1) {
            return [$html, null];
        }

        $footer = trim($matches[1]);
        $html = preg_replace('/<template\s+data-pdf-footer[^>]*>.*?<\/template>/is', '', $html, 1) ?? $html;

        return [$html, $footer === '' ? null : $footer];
    }
}
