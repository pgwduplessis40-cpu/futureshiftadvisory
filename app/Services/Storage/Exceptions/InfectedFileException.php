<?php

declare(strict_types=1);

namespace App\Services\Storage\Exceptions;

use App\Services\Integration\VirusScanner\ScanResult;
use RuntimeException;

final class InfectedFileException extends RuntimeException
{
    public function __construct(public readonly ScanResult $scanResult)
    {
        parent::__construct(sprintf(
            'Upload rejected because malware was detected%s.',
            $scanResult->signature === null ? '' : ': '.$scanResult->signature,
        ));
    }
}
