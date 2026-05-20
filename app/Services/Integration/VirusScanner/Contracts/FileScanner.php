<?php

declare(strict_types=1);

namespace App\Services\Integration\VirusScanner\Contracts;

use App\Services\Integration\VirusScanner\ScanResult;

interface FileScanner
{
    /**
     * @param  resource  $stream
     */
    public function scan(mixed $stream): ScanResult;
}
