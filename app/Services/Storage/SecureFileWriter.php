<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Document;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class SecureFileWriter
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly AuditWriter $auditWriter,
        private readonly SecureStorageNotice $notice,
        private readonly UploadThreatInspector $threatInspector,
    ) {}

    /**
     * Persist a trusted, server-generated artifact through the encrypted
     * secure disk. Generated report files never enter the upload/scanning
     * workflow, but they must retain the same encrypted-at-rest boundary.
     */
    public function writeGenerated(string $path, string $contents): void
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || in_array('..', explode('/', $path), true)
        ) {
            throw new SecureFileStorageException('Generated artifact path must be a safe relative path.');
        }

        $written = Storage::disk('secure_local')->put($path, $contents);

        if ($written !== true) {
            throw new SecureFileStorageException('Secure disk rejected the generated artifact write.');
        }
    }

    public function write(
        UploadedFile $uploadedFile,
        ?Authenticatable $owner,
        string $category,
        ?string $clientId = null,
        ?string $entrepreneurProfileId = null,
        ?string $npoEngagementId = null,
    ): Document {
        $realPath = $uploadedFile->getRealPath();
        if (! is_string($realPath) || ! is_file($realPath)) {
            throw new SecureFileStorageException('Uploaded file is not readable.');
        }

        $scanResult = $this->threatInspector->inspect($uploadedFile, $realPath) ?? $this->scan($realPath);
        if ($scanResult->isError() && $this->canFailOpenScannerError()) {
            $scanResult = ScanResult::clean([
                'engine' => 'development-fail-open',
                'original_error' => $scanResult->toPayload(),
            ]);
        }

        if ($scanResult->isInfected()) {
            $this->recordInfectedRejection($uploadedFile, $owner, $category, $clientId, $scanResult);

            throw new InfectedFileException($scanResult);
        }

        $storedPath = $this->storedPath(
            category: $category,
            uploadedFile: $uploadedFile,
            quarantined: $scanResult->isError(),
        );
        $contents = file_get_contents($realPath);
        if ($contents === false) {
            throw new SecureFileStorageException('Uploaded file could not be read for persistence.');
        }

        $normalisedCategory = $this->normaliseCategory($category);
        $sha256 = hash('sha256', $contents);
        $existing = $this->existingDocument(
            owner: $owner,
            category: $normalisedCategory,
            sha256: $sha256,
            clientId: $clientId,
            entrepreneurProfileId: $entrepreneurProfileId,
            npoEngagementId: $npoEngagementId,
        );

        if ($existing instanceof Document) {
            if ($scanResult->isClean() && $existing->scanner_result !== Document::SCANNER_CLEAN) {
                $before = [
                    'scanner_result' => $existing->scanner_result,
                    'scanner_payload' => $existing->scanner_payload,
                ];

                $existing->forceFill([
                    'scanner_result' => Document::SCANNER_CLEAN,
                    'scanner_payload' => $scanResult->toPayload(),
                ])->save();

                $this->auditWriter->record(
                    action: 'document.scan_recovered',
                    subject: $existing,
                    before: $before,
                    after: [
                        'scanner_result' => $existing->scanner_result,
                        'scanner_payload' => $existing->scanner_payload,
                    ],
                );
            }

            return $existing->refresh();
        }

        $written = Storage::disk('secure_local')->put($storedPath, $contents);
        if ($written !== true) {
            throw new SecureFileStorageException('Secure disk rejected the encrypted write.');
        }

        $document = Document::query()->create([
            'client_id' => $clientId,
            'entrepreneur_profile_id' => $entrepreneurProfileId,
            'npo_engagement_id' => $npoEngagementId,
            'category' => $normalisedCategory,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'stored_path' => $storedPath,
            'byte_size' => strlen($contents),
            'mime_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
            'sha256' => $sha256,
            'uploaded_by_user_id' => $owner?->getAuthIdentifier(),
            'scanner_result' => $scanResult->isError() ? Document::SCANNER_ERROR : Document::SCANNER_CLEAN,
            'scanner_payload' => $scanResult->toPayload(),
        ]);

        if ($scanResult->isError()) {
            $this->auditWriter->record(
                action: 'document.upload_quarantined',
                subject: $document,
                after: [
                    'stored_path' => $storedPath,
                    'scanner' => $scanResult->toPayload(),
                ],
            );
            $this->notice->recordScannerError($document, $scanResult);

            return $document;
        }

        $this->auditWriter->record(
            action: 'document.uploaded',
            subject: $document,
            after: [
                'stored_path' => $storedPath,
                'scanner' => $scanResult->toPayload(),
            ],
        );

        return $document;
    }

    private function existingDocument(
        ?Authenticatable $owner,
        string $category,
        string $sha256,
        ?string $clientId,
        ?string $entrepreneurProfileId,
        ?string $npoEngagementId,
    ): ?Document {
        $query = Document::query()
            ->where('category', $category)
            ->where('sha256', $sha256)
            ->whereIn('scanner_result', [
                Document::SCANNER_PENDING,
                Document::SCANNER_CLEAN,
                Document::SCANNER_ERROR,
            ]);

        foreach ([
            'client_id' => $clientId,
            'entrepreneur_profile_id' => $entrepreneurProfileId,
            'npo_engagement_id' => $npoEngagementId,
        ] as $column => $value) {
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        if ($clientId === null && $entrepreneurProfileId === null && $npoEngagementId === null) {
            $ownerId = $owner?->getAuthIdentifier();
            $ownerId === null
                ? $query->whereNull('uploaded_by_user_id')
                : $query->where('uploaded_by_user_id', $ownerId);
        }

        return $query
            ->orderByRaw("CASE WHEN scanner_result = 'clean' THEN 0 ELSE 1 END")
            ->oldest()
            ->first();
    }

    private function scan(string $path): ScanResult
    {
        $stream = fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw new SecureFileStorageException('Uploaded file could not be opened for malware scanning.');
        }

        try {
            return $this->scanner->scan($stream);
        } finally {
            fclose($stream);
        }
    }

    private function recordInfectedRejection(
        UploadedFile $uploadedFile,
        ?Authenticatable $owner,
        string $category,
        ?string $clientId,
        ScanResult $scanResult,
    ): void {
        try {
            $this->auditWriter->record(
                action: 'document.upload_rejected.infected',
                subject: null,
                context: [
                    'client_id' => $clientId,
                    'uploaded_by_user_id' => $owner?->getAuthIdentifier(),
                    'category' => $this->normaliseCategory($category),
                    'original_filename' => $uploadedFile->getClientOriginalName(),
                    'scanner' => $scanResult->toPayload(),
                ],
            );
        } catch (Throwable) {
            report(new SecureFileStorageException('Failed to audit infected upload rejection.'));
        }
    }

    private function storedPath(string $category, UploadedFile $uploadedFile, bool $quarantined): string
    {
        $prefix = $quarantined ? 'quarantine' : 'documents';
        $extension = $uploadedFile->getClientOriginalExtension();
        $base = Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'upload';
        $filename = Str::uuid().'-'.$base.($extension === '' ? '' : '.'.strtolower($extension));

        return implode('/', [
            $prefix,
            $this->normaliseCategory($category),
            now()->format('Y/m'),
            $filename,
        ]);
    }

    private function normaliseCategory(string $category): string
    {
        $normalised = Str::of($category)->lower()->replace(['-', ' '], '_')->value();

        return in_array($normalised, [
            Document::CATEGORY_FINANCIAL_STATEMENT,
            Document::CATEGORY_CONTRACT,
            Document::CATEGORY_INSURANCE_CERTIFICATE,
            Document::CATEGORY_HR_RECORD,
            Document::CATEGORY_COMPLIANCE_DOC,
            Document::CATEGORY_PLAN_ATTACHMENT,
            Document::CATEGORY_DD_ARTIFACT,
            Document::CATEGORY_MESSAGE_ATTACHMENT,
            Document::CATEGORY_NPO_MEETING_MINUTES,
            Document::CATEGORY_NPO_BOARD_RECORD,
            Document::CATEGORY_INSPIRATION_IMAGE,
            Document::CATEGORY_TEMPLATE_FILE,
            Document::CATEGORY_REFERENCE_DATA_EVIDENCE,
            Document::CATEGORY_OTHER,
        ], true) ? $normalised : Document::CATEGORY_OTHER;
    }

    private function canFailOpenScannerError(): bool
    {
        return (bool) config('virus-scanner.fail_open_on_error', false)
            && (bool) config('virus-scanner.allow_noop', false)
            && in_array((string) config('app.env', 'production'), ['local', 'testing'], true);
    }
}
