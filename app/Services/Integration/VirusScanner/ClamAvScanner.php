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

        $timeout = (float) Config::get('virus-scanner.clamav.timeout_seconds', 2);
        $chunkSize = max(1024, (int) Config::get('virus-scanner.clamav.chunk_size', 8192));

        [$socket, $endpoint, $connectionErrors] = $this->connect($timeout);

        if (! is_resource($socket)) {
            return ScanResult::error('ClamAV daemon unavailable.', [
                'engine' => 'clamav',
                'connection_errors' => $connectionErrors,
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
                    'endpoint' => $endpoint,
                    'response' => $response,
                ]);
            }

            if (str_contains($response, 'OK')) {
                return ScanResult::clean([
                    'engine' => 'clamav',
                    'endpoint' => $endpoint,
                    'response' => $response,
                ]);
            }

            return ScanResult::error('ClamAV returned an unrecognised scan response.', [
                'engine' => 'clamav',
                'endpoint' => $endpoint,
                'response' => $response,
            ]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return array{0: mixed, 1: ?string, 2: array<int, array<string, mixed>>}
     */
    private function connect(float $timeout): array
    {
        $endpoints = [];
        $socketPath = trim((string) Config::get('virus-scanner.clamav.socket', ''));

        if ($socketPath !== '') {
            $endpoints[] = [
                'name' => 'unix',
                'address' => 'unix://'.$socketPath,
            ];
        }

        $host = trim((string) Config::get('virus-scanner.clamav.host', '127.0.0.1'));
        $port = (int) Config::get('virus-scanner.clamav.port', 3310);

        if ($host !== '' && $port > 0) {
            $endpoints[] = [
                'name' => 'tcp',
                'address' => sprintf('tcp://%s:%d', $host, $port),
            ];
        }

        $errors = [];

        foreach ($endpoints as $endpoint) {
            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                $endpoint['address'],
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
            );

            if (is_resource($socket)) {
                return [$socket, $endpoint['name'], $errors];
            }

            $errors[] = [
                'endpoint' => $endpoint['name'],
                'errno' => $errno,
                'error' => $errstr,
            ];
        }

        return [null, null, $errors];
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
