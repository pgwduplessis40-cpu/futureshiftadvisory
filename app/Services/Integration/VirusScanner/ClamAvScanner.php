<?php

declare(strict_types=1);

namespace App\Services\Integration\VirusScanner;

use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use Illuminate\Support\Facades\Config;

final class ClamAvScanner implements FileScanner
{
    public function scan(mixed $stream): ScanResult
    {
        if (! is_resource($stream)) {
            return ScanResult::error('ClamAV scan received an invalid stream.', [
                'engine' => 'clamav',
            ]);
        }

        $host = (string) Config::get('virus-scanner.clamav.host', '127.0.0.1');
        $port = (int) Config::get('virus-scanner.clamav.port', 3310);
        $timeout = (float) Config::get('virus-scanner.clamav.timeout_seconds', 2);
        $chunkSize = max(1024, (int) Config::get('virus-scanner.clamav.chunk_size', 8192));

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! is_resource($socket)) {
            return ScanResult::error('ClamAV daemon unavailable.', [
                'engine' => 'clamav',
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'error' => $errstr,
            ]);
        }

        stream_set_timeout($socket, (int) ceil($timeout));

        if ($this->isSeekable($stream)) {
            rewind($stream);
        }

        try {
            if (! $this->writeAll($socket, "zINSTREAM\0")) {
                return ScanResult::error('Failed to start ClamAV INSTREAM scan.', [
                    'engine' => 'clamav',
                ]);
            }

            while (! feof($stream)) {
                $chunk = fread($stream, $chunkSize);
                if ($chunk === false) {
                    return ScanResult::error('Failed to read upload stream for ClamAV scan.', [
                        'engine' => 'clamav',
                    ]);
                }

                if ($chunk === '') {
                    continue;
                }

                if (! $this->writeAll($socket, pack('N', strlen($chunk)).$chunk)) {
                    return ScanResult::error('Failed to stream upload bytes to ClamAV.', [
                        'engine' => 'clamav',
                    ]);
                }
            }

            if (! $this->writeAll($socket, pack('N', 0))) {
                return ScanResult::error('Failed to finish ClamAV INSTREAM scan.', [
                    'engine' => 'clamav',
                ]);
            }

            $response = fgets($socket);
            if (! is_string($response) || $response === '') {
                return ScanResult::error('ClamAV returned an empty scan response.', [
                    'engine' => 'clamav',
                ]);
            }

            $response = trim($response, "\0\r\n ");

            if (str_contains($response, 'FOUND')) {
                return ScanResult::infected($this->signatureFromResponse($response), [
                    'engine' => 'clamav',
                    'response' => $response,
                ]);
            }

            if (str_contains($response, 'OK')) {
                return ScanResult::clean([
                    'engine' => 'clamav',
                    'response' => $response,
                ]);
            }

            return ScanResult::error('ClamAV returned an unrecognised scan response.', [
                'engine' => 'clamav',
                'response' => $response,
            ]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param  resource  $socket
     */
    private function writeAll(mixed $socket, string $bytes): bool
    {
        $remaining = strlen($bytes);
        $offset = 0;

        while ($remaining > 0) {
            $written = fwrite($socket, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                return false;
            }

            $offset += $written;
            $remaining -= $written;
        }

        return true;
    }

    /**
     * @param  resource  $stream
     */
    private function isSeekable(mixed $stream): bool
    {
        $meta = stream_get_meta_data($stream);

        return (bool) ($meta['seekable'] ?? false);
    }

    private function signatureFromResponse(string $response): string
    {
        $signature = preg_replace('/^stream: | FOUND$/', '', $response);

        return is_string($signature) && $signature !== '' ? $signature : 'unknown';
    }
}
