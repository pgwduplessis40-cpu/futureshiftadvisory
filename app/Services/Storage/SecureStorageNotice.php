<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Document;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\VirusScanner\ScanResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SecureStorageNotice
{
    public const CACHE_KEY = 'fsa.secure_storage.latest_scan_error_notice';

    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function recordScannerError(Document $document, ScanResult $scanResult): void
    {
        $payload = [
            'message' => 'A document upload was quarantined because malware scanning could not complete.',
            'document_id' => $document->id,
            'client_id' => $document->client_id,
            'scanner_result' => $scanResult->result,
            'scanner_payload' => $scanResult->toPayload(),
            'recorded_at' => now()->toIso8601String(),
        ];

        Cache::put(
            self::CACHE_KEY,
            $payload,
            now()->addSeconds((int) Config::get('virus-scanner.notice_cache_ttl_seconds', 86400)),
        );
        Log::warning('document.scan_error.quarantined', $payload);

        try {
            $this->auditWriter->record(
                action: 'document.scan_error.advisor_notice',
                subject: $document,
                after: $payload,
            );
        } catch (Throwable $e) {
            Log::warning('Failed to persist document scan-error advisor notice audit event', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
