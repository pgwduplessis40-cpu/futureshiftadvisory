<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Models\Document;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\NoopScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\SecureFileWriter;
use App\Services\Storage\SecureStorageNotice;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
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
