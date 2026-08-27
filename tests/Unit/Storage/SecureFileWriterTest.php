<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Jobs\VerifyDocumentJob;
use App\Models\Document;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\NoopScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use App\Services\Storage\SecureFileWriter;
use App\Services\Storage\SecureStorageNotice;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SecureFileWriterTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/secure-storage');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');
        Cache::flush();

        app(RequestContext::class)->apply(RequestContext::ROLE_SUPER_ADMIN, []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_secure_file_writer_persists_encrypted_bytes_and_document_metadata(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $plaintext = 'Cashflow forecast with sensitive notes.';

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('forecast.txt', $plaintext),
            owner: $user,
            category: Document::CATEGORY_FINANCIAL_STATEMENT,
            clientId: $clientId,
        );

        $rawBytes = file_get_contents(Storage::disk('secure_local')->path($document->stored_path));

        $this->assertIsString($rawBytes);
        $this->assertStringNotContainsString($plaintext, $rawBytes);
        $this->assertSame($plaintext, Storage::disk('secure_local')->get($document->stored_path));
        $this->assertSame(Document::SCANNER_CLEAN, $document->scanner_result);
        $this->assertTrue($document->isVisibleToClients());
        $this->assertSame(hash('sha256', $plaintext), $document->sha256);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.uploaded',
            'subject_id' => $document->id,
        ]);
    }

    public function test_secure_file_writer_persists_a_generated_artifact_on_the_encrypted_disk(): void
    {
        $path = 'reports/'.Str::uuid().'/artifact.pdf';
        $plaintext = 'Generated report with confidential financial analysis.';

        app(SecureFileWriter::class)->writeGenerated($path, $plaintext);

        $rawBytes = file_get_contents(Storage::disk('secure_local')->path($path));

        $this->assertIsString($rawBytes);
        $this->assertStringNotContainsString($plaintext, $rawBytes);
        $this->assertSame($plaintext, Storage::disk('secure_local')->get($path));
    }

    public function test_secure_file_writer_rejects_an_unsafe_generated_artifact_path(): void
    {
        $this->expectException(SecureFileStorageException::class);

        app(SecureFileWriter::class)->writeGenerated('../reports/artifact.pdf', 'Report');
    }

    public function test_noop_scanner_allows_eicar_fixture_in_development_mode(): void
    {
        $stream = fopen('php://temp', 'r+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
        rewind($stream);

        $result = (new NoopScanner)->scan($stream);
        fclose($stream);

        $this->assertTrue($result->isClean());
        $this->assertSame('noop', $result->payload['engine']);
    }

    public function test_infected_scan_rejects_upload_without_persisting_file(): void
    {
        $this->bindScanner(ScanResult::infected('Eicar-Test-Signature', ['engine' => 'fake-clamav']));

        try {
            app(SecureFileWriter::class)->write(
                uploadedFile: UploadedFile::fake()->createWithContent(
                    'eicar.txt',
                    'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*',
                ),
                owner: User::factory()->create(),
                category: Document::CATEGORY_OTHER,
                clientId: (string) Str::uuid(),
            );

            $this->fail('Expected infected uploads to be rejected.');
        } catch (InfectedFileException $e) {
            $this->assertSame('Eicar-Test-Signature', $e->scanResult->signature);
        }

        $this->assertSame(0, Document::query()->count());
        $this->assertSame([], Storage::disk('secure_local')->allFiles());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.upload_rejected.infected',
        ]);
    }

    public function test_scanner_error_persists_quarantined_document_and_raises_advisor_notice(): void
    {
        $this->bindScanner(ScanResult::error('daemon offline', ['engine' => 'fake-clamav']));
        $plaintext = 'Document held while scanner is unavailable.';

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('contract.txt', $plaintext),
            owner: User::factory()->create(),
            category: Document::CATEGORY_CONTRACT,
            clientId: (string) Str::uuid(),
        );

        $this->assertSame(Document::SCANNER_ERROR, $document->scanner_result);
        $this->assertFalse($document->isVisibleToClients());
        $this->assertSame(0, Document::visibleToClients()->count());
        $this->assertStringStartsWith('quarantine/contract/', $document->stored_path);
        $this->assertSame($plaintext, Storage::disk('secure_local')->get($document->stored_path));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.upload_quarantined',
            'subject_id' => $document->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.scan_error.advisor_notice',
            'subject_id' => $document->id,
        ]);

        $notice = Cache::get(SecureStorageNotice::CACHE_KEY);

        $this->assertIsArray($notice);
        $this->assertSame($document->id, $notice['document_id']);
    }

    public function test_local_scanner_error_can_fail_open_when_noop_is_allowed(): void
    {
        Config::set('app.env', 'local');
        Config::set('virus-scanner.allow_noop', true);
        Config::set('virus-scanner.fail_open_on_error', true);
        $this->bindScanner(ScanResult::error('daemon offline', ['engine' => 'fake-clamav']));

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('letterhead.docx', 'clean local fixture'),
            owner: User::factory()->create(),
            category: Document::CATEGORY_TEMPLATE_FILE,
        );

        $this->assertSame(Document::SCANNER_CLEAN, $document->scanner_result);
        $this->assertTrue($document->isVisibleToClients());
        $this->assertStringStartsWith('documents/template_file/', $document->stored_path);
        $this->assertSame('development-fail-open', $document->scanner_payload['payload']['engine']);
        $this->assertSame('daemon offline', $document->scanner_payload['payload']['original_error']['message']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.uploaded',
            'subject_id' => $document->id,
        ]);
    }

    public function test_production_scanner_error_still_quarantines_even_if_fail_open_is_set(): void
    {
        Config::set('app.env', 'production');
        Config::set('virus-scanner.allow_noop', true);
        Config::set('virus-scanner.fail_open_on_error', true);
        $this->bindScanner(ScanResult::error('daemon offline', ['engine' => 'fake-clamav']));

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('contract.txt', 'production fixture'),
            owner: User::factory()->create(),
            category: Document::CATEGORY_CONTRACT,
        );

        $this->assertSame(Document::SCANNER_ERROR, $document->scanner_result);
        $this->assertStringStartsWith('quarantine/contract/', $document->stored_path);
        $this->assertFalse($document->isVisibleToClients());
    }

    public function test_repeated_upload_reuses_and_recovers_the_existing_quarantined_document(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        $contents = 'The same business plan attachment.';

        $this->bindScanner(ScanResult::error('daemon offline', ['engine' => 'fake-clamav']));

        $first = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('business-plan.docx', $contents),
            owner: $user,
            category: Document::CATEGORY_PLAN_ATTACHMENT,
            clientId: $clientId,
        );
        $repeated = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('business-plan.docx', $contents),
            owner: $user,
            category: Document::CATEGORY_PLAN_ATTACHMENT,
            clientId: $clientId,
        );

        $this->assertSame($first->getKey(), $repeated->getKey());
        $this->assertSame(1, Document::query()->count());

        $this->bindScanner(ScanResult::clean(['engine' => 'fake-clamav']));

        $recovered = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('business-plan.docx', $contents),
            owner: $user,
            category: Document::CATEGORY_PLAN_ATTACHMENT,
            clientId: $clientId,
        );

        $this->assertSame($first->getKey(), $recovered->getKey());
        $this->assertSame(Document::SCANNER_CLEAN, $recovered->scanner_result);
        $this->assertSame(1, Document::query()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.scan_recovered',
            'subject_id' => $first->getKey(),
        ]);
    }

    public function test_rescan_command_probes_scanner_and_recovers_quarantined_document(): void
    {
        Queue::fake();
        $this->bindScanner(ScanResult::error('daemon offline', ['engine' => 'fake-clamav']));

        $document = app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('business-plan.docx', 'Recover this document.'),
            owner: User::factory()->create(),
            category: Document::CATEGORY_PLAN_ATTACHMENT,
        );
        $duplicatePath = 'quarantine/plan_attachment/'.Str::uuid().'.docx';
        Storage::disk('secure_local')->put($duplicatePath, 'Recover this document.');
        $duplicate = Document::query()->create([
            'category' => $document->category,
            'original_filename' => $document->original_filename,
            'stored_path' => $duplicatePath,
            'byte_size' => $document->byte_size,
            'mime_type' => $document->mime_type,
            'sha256' => $document->sha256,
            'uploaded_by_user_id' => $document->uploaded_by_user_id,
            'scanner_result' => Document::SCANNER_ERROR,
            'scanner_payload' => $document->scanner_payload,
        ]);
        $referencedPath = 'quarantine/reference_data_evidence/'.Str::uuid().'.docx';
        Storage::disk('secure_local')->put($referencedPath, 'Recover this document.');
        $referenced = Document::query()->create([
            'category' => $document->category,
            'original_filename' => $document->original_filename,
            'stored_path' => $referencedPath,
            'byte_size' => $document->byte_size,
            'mime_type' => $document->mime_type,
            'sha256' => $document->sha256,
            'uploaded_by_user_id' => $document->uploaded_by_user_id,
            'scanner_result' => Document::SCANNER_ERROR,
            'scanner_payload' => $document->scanner_payload,
        ]);
        $learningUpdate = LearningUpdate::query()->create([
            'layer_id' => 1,
            'source' => ['type' => 'test'],
            'summary' => 'Test reference data evidence.',
        ]);
        $referenceEntry = ReferenceDataEntry::query()->create([
            'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
            'payload' => ['indicator' => 'ocr', 'value' => 2.25],
            'as_at' => now()->toDateString(),
            'source' => 'test',
            'entered_by_user_id' => $document->uploaded_by_user_id,
            'learning_update_id' => $learningUpdate->getKey(),
            'evidence_document_id' => $referenced->getKey(),
        ]);

        $this->app->instance(FileScanner::class, new class implements FileScanner
        {
            public function scan(mixed $stream): ScanResult
            {
                $contents = stream_get_contents($stream);

                return is_string($contents) && str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')
                    ? ScanResult::infected('Eicar-Test-Signature', ['engine' => 'fake-clamav'])
                    : ScanResult::clean(['engine' => 'fake-clamav']);
            }
        });

        $this->artisan('fsa:rescan-quarantined-documents', [
            '--probe' => true,
            '--fail-on-error' => true,
        ])->assertSuccessful();

        $this->assertSame(Document::SCANNER_CLEAN, $document->refresh()->scanner_result);
        $this->assertDatabaseMissing('documents', ['id' => $duplicate->getKey()]);
        Storage::disk('secure_local')->assertMissing($duplicatePath);
        $this->assertSame(Document::SCANNER_CLEAN, $referenced->refresh()->scanner_result);
        $this->assertDatabaseHas('reference_data_entries', [
            'id' => $referenceEntry->getKey(),
            'evidence_document_id' => $referenced->getKey(),
        ]);
        Storage::disk('secure_local')->assertExists($referencedPath);
        Queue::assertPushed(VerifyDocumentJob::class);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.scan_recovered',
            'subject_id' => $document->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.quarantine_duplicate_removed',
            'subject_id' => $duplicate->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'document.scan_recovered',
            'subject_id' => $referenced->getKey(),
        ]);
    }

    private function bindScanner(ScanResult $result): void
    {
        $this->app->instance(FileScanner::class, new class($result) implements FileScanner
        {
            public function __construct(private readonly ScanResult $result) {}

            public function scan(mixed $stream): ScanResult
            {
                return $this->result;
            }
        });
    }
}
