<?php

declare(strict_types=1);

namespace App\Services\Integration\VirusScanner;

use App\Services\Integration\VirusScanner\Contracts\FileScanner;

final class UnavailableScanner implements FileScanner
{
    public function scan(mixed $stream): ScanResult
    {
        return ScanResult::error('Live malware scanner is not configured.', [
            'engine' => 'configuration_guard',
            'live' => false,
            'allow_noop' => false,
        ]);
    }
}
