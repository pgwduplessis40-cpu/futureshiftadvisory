<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Storage\UploadThreatInspector;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

final class UploadThreatInspectorTest extends TestCase
{
    public function test_allows_plain_pdf_without_active_content(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'cashflow.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj",
        );

        $this->assertNull($this->inspect($file));
    }

    public function test_rejects_executable_payload_disguised_as_document(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'supplier-contract.pdf',
            "MZ\x90\x00fake executable payload",
        );

        $result = $this->inspect($file);

        $this->assertNotNull($result);
        $this->assertTrue($result->isInfected());
        $this->assertSame('executable-file-signature', $result->signature);
        $this->assertSame('upload-threat-inspector', $result->payload['engine']);
    }

    public function test_rejects_active_pdf_payloads(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'signed-terms.pdf',
            "%PDF-1.4\n1 0 obj\n<< /OpenAction << /S /JavaScript /JS (app.alert('x')) >> >>",
        );

        $result = $this->inspect($file);

        $this->assertNotNull($result);
        $this->assertTrue($result->isInfected());
        $this->assertSame('active-pdf-content', $result->signature);
    }

    public function test_rejects_office_macro_projects(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required to build an OOXML macro fixture.');
        }

        $path = tempnam(sys_get_temp_dir(), 'fsa-threat-docx-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $zip->addFromString('word/vbaProject.bin', 'macro bytes');
        $zip->close();

        try {
            $file = new UploadedFile(
                path: $path,
                originalName: 'board-pack.docx',
                mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                test: true,
            );

            $result = $this->inspect($file);

            $this->assertNotNull($result);
            $this->assertTrue($result->isInfected());
            $this->assertSame('office-macro-project', $result->signature);
        } finally {
            @unlink($path);
        }
    }

    private function inspect(UploadedFile $file): ?ScanResult
    {
        $path = $file->getRealPath();
        $this->assertIsString($path);

        return app(UploadThreatInspector::class)->inspect($file, $path);
    }
}
