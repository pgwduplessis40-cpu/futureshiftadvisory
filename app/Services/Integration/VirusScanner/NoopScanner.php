<?php

declare(strict_types=1);

namespace App\Services\Integration\VirusScanner;

use App\Services\Integration\VirusScanner\Contracts\FileScanner;

final class NoopScanner implements FileScanner
{
    public function scan(mixed $stream): ScanResult
    {
        return ScanResult::clean([
            'engine' => 'noop',
            'live' => false,
        ]);
    }
}
