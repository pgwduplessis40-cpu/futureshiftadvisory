<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Jobs\VerifyDocumentJob;
use App\Models\Document;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class QuarantinedDocumentRescanner
{
    private const EICAR_TEST_SIGNATURE = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function __construct(
        private readonly FileScanner $scanner,
        private readonly AuditWriter $auditWriter,
        private readonly SecureStorageNotice $notice,
    ) {}

    public function probe(): ScanResult
    {
        return $this->scanBytes(self::EICAR_TEST_SIGNATURE);
    }

    public function rescan(Document $document): ScanResult
    {
        if ($document->scanner_result !== Document::SCANNER_ERROR) {
            return ScanResult::error('Only documents with a scanner error can be rescanned.');
        }

        $disk = Storage::disk('secure_local');

        try {
            if (! $disk->exists($document->stored_path)) {
                return $this->recordError($document, ScanResult::error(
                    'Quarantined document bytes are missing.',
                    ['engine' => 'secure-storage'],
                ));
            }

            $contents = $disk->get($document->stored_path);
        } catch (Throwable) {
            return $this->recordError($document, ScanResult::error(
                'Quarantined document bytes could not be read.',
                ['engine' => 'secure-storage'],
            ));
        }

        $result = $this->scanBytes($contents);
        $before = [
            'scanner_result' => $document->scanner_result,
            'scanner_payload' => $document->scanner_payload,
        ];

        if ($result->isError()) {
            return $this->recordError($document, $result);
        }

        DB::transaction(function () use ($document, $result, $before): void {
            $document->forceFill([
                'scanner_result' => $result->isClean()
                    ? Document::SCANNER_CLEAN
                    : Document::SCANNER_INFECTED,
                'scanner_payload' => $result->toPayload(),
            ])->save();

            $this->auditWriter->record(
                action: $result->isClean()
                    ? 'document.scan_recovered'
                    : 'document.scan_rejected.infected',
                subject: $document,
                before: $before,
                after: [
                    'scanner_result' => $document->scanner_result,
                    'scanner_payload' => $document->scanner_payload,
                ],
            );
        });

        if ($result->isClean() && ! $document->verifications()->exists()) {
            VerifyDocumentJob::dispatch((string) $document->getKey(), []);
        }

        return $result;
    }

    public function discardDuplicate(Document $duplicate, Document $canonical): bool
    {
        if (
            $duplicate->scanner_result !== Document::SCANNER_ERROR
            || $duplicate->verifications()->exists()
        ) {
            return false;
        }

        $storedPath = $duplicate->stored_path;

        DB::transaction(function () use ($duplicate, $canonical): void {
            $this->auditWriter->record(
                action: 'document.quarantine_duplicate_removed',
                subject: $duplicate,
                before: [
                    'scanner_result' => $duplicate->scanner_result,
                    'sha256' => $duplicate->sha256,
                ],
                after: [
                    'canonical_document_id' => $canonical->getKey(),
                ],
            );

            $duplicate->delete();
        });

        Storage::disk('secure_local')->delete($storedPath);

        return true;
    }

    private function recordError(Document $document, ScanResult $result): ScanResult
    {
        $payload = $result->toPayload();
        $payloadChanged = $document->scanner_payload !== $payload;

        $document->forceFill([
            'scanner_result' => Document::SCANNER_ERROR,
            'scanner_payload' => $payload,
        ])->save();

        if ($payloadChanged) {
            $this->notice->recordScannerError($document, $result);
        }

        return $result;
    }

    private function scanBytes(string $contents): ScanResult
    {
        $stream = fopen('php://temp', 'r+b');
        if (! is_resource($stream)) {
            return ScanResult::error('Unable to prepare document bytes for malware scanning.');
        }

        try {
            if (fwrite($stream, $contents) === false) {
                return ScanResult::error('Unable to prepare document bytes for malware scanning.');
            }

            rewind($stream);

            return $this->scanner->scan($stream);
        } finally {
            fclose($stream);
        }
    }
}
