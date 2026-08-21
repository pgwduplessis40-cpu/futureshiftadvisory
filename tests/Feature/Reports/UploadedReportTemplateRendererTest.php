<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Template;
use App\Services\Reports\UploadedReportTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class UploadedReportTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('secure_local');
    }

    public function test_docx_template_renders_header_sections_footer_and_document_styles(): void
    {
        $path = 'documents/templates/rich-report-template.docx';
        Storage::disk('secure_local')->put($path, $this->docx(
            document: <<<'XML'
<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>{{ client_name }}</w:t></w:r></w:p>
<w:p><w:pPr><w:pStyle w:val="Heading2"/><w:jc w:val="center"/><w:spacing w:val="160" w:after="80"/><w:ind w:left="240"/></w:pPr><w:r><w:rPr><w:i/><w:color w:val="1C2B45"/><w:sz w:val="28"/></w:rPr><w:t>Decision summary</w:t></w:r></w:p>
<w:p><w:r><w:t>{{ sections }}</w:t></w:r></w:p>
<w:tbl><w:tblPr><w:tblW w:w="5000"/><w:jc w:val="center"/><w:tblBorders><w:top w:val="single" w:sz="8" w:color="B8860B"/></w:tblBorders></w:tblPr><w:tr><w:tc><w:tcPr><w:tcW w:w="2500"/><w:shd w:fill="F4F1EA"/></w:tcPr><w:p><w:r><w:t>Metric</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
XML,
            header: '<w:p><w:r><w:t>Prepared for {{ client_name }}</w:t></w:r></w:p>',
            footer: '<w:p><w:r><w:t>Confidential - Page 1</w:t></w:r></w:p>',
        ));
        $template = $this->uploadedTemplate($path);

        $html = app(UploadedReportTemplateRenderer::class)->renderDocument(
            '<Unsafe & title>',
            $template,
            '<section id="generated">Generated sections</section>',
            ['{{ client_name }}' => 'Acme &amp; Co'],
            '.report-content { color: #123456; }',
        );

        $this->assertIsString($html);
        $this->assertStringContainsString('<title>&lt;Unsafe &amp; title&gt;</title>', $html);
        $this->assertStringContainsString('data-report-template-source="uploaded-docx"', $html);
        $this->assertStringContainsString('Prepared for Acme &amp; Co', $html);
        $this->assertStringContainsString('<h1 class="docx-template-block"', $html);
        $this->assertStringContainsString('<h2 class="docx-template-block"', $html);
        $this->assertStringContainsString('text-align: center;', $html);
        $this->assertStringContainsString('font-weight: 700;', $html);
        $this->assertStringContainsString('font-style: italic;', $html);
        $this->assertStringContainsString('background-color: #F4F1EA;', $html);
        $this->assertStringContainsString('{{ sections }}', $html);
        $this->assertStringContainsString('data-pdf-footer', $html);
        $this->assertStringContainsString('<span class="pageNumber"></span>', $html);
    }

    public function test_docx_template_without_section_token_appends_generated_sections_and_standalone_fragment(): void
    {
        $bytes = $this->docx(
            document: '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="8" w:color="B8860B"/></w:pBdr></w:pPr></w:p><w:p><w:r><w:t>Body copy</w:t></w:r></w:p>',
            header: '',
            footer: '',
        );
        $path = 'documents/templates/no-sections-token.docx';
        Storage::disk('secure_local')->put($path, $bytes);
        $template = $this->uploadedTemplate($path, ['original_name' => 'No Sections Token.docx']);
        $renderer = app(UploadedReportTemplateRenderer::class);

        $html = $renderer->renderDocument('Report', $template, '<article>Generated report body</article>', [], '');
        $standalone = $renderer->renderStandaloneFragmentFromBytes($bytes);

        $this->assertIsString($html);
        $this->assertStringContainsString('docx-page-break', $html);
        $this->assertStringContainsString('<main class="report-content"><article>Generated report body</article></main>', $html);
        $this->assertStringContainsString('docx-template-rule', $html);
        $this->assertIsString($standalone);
        $this->assertStringContainsString('uploaded-docx-standalone', $standalone);
        $this->assertStringContainsString('Body copy', $standalone);
    }

    public function test_renderer_rejects_non_docx_unscanned_missing_and_invalid_templates(): void
    {
        $renderer = app(UploadedReportTemplateRenderer::class);
        $plain = Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Plain template',
            'body' => '<p>Plain</p>',
            'structure' => ['source_kind' => 'editor'],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);
        $unsafe = $this->uploadedTemplate('documents/templates/missing.docx', [
            'scanner_result' => 'infected',
        ]);
        $invalidPath = 'documents/templates/invalid.docx';
        Storage::disk('secure_local')->put($invalidPath, 'not a DOCX archive');
        $invalid = $this->uploadedTemplate($invalidPath);

        $this->assertFalse($renderer->supports($plain));
        $this->assertFalse($renderer->supports($unsafe));
        $this->assertNull($renderer->renderDocument('Report', $plain, '', [], ''));
        $this->assertNull($renderer->renderDocument('Report', $unsafe, '', [], ''));
        $this->assertNull($renderer->renderDocument('Report', $invalid, '', [], ''));
        $this->assertNull($renderer->renderStandaloneFragmentFromBytes('not a DOCX archive'));
    }

    /**
     * @param  array<string, mixed>  $uploadedFile
     */
    private function uploadedTemplate(string $path, array $uploadedFile = []): Template
    {
        return Template::query()->create([
            'category' => Template::CATEGORY_REPORT,
            'title' => 'Uploaded report template '.fake()->unique()->word(),
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'stored_path' => $path,
                    'original_name' => 'Uploaded Report Template.docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'extension' => 'docx',
                    'scanner_result' => 'clean',
                    ...$uploadedFile,
                ],
            ],
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
        ]);
    }

    private function docx(string $document, string $header, string $footer): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-uploaded-report-template-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $this->wordXml($document));

        if ($header !== '') {
            $zip->addFromString('word/header1.xml', $this->wordXml($header));
        }

        if ($footer !== '') {
            $zip->addFromString('word/footer1.xml', $this->wordXml($footer));
        }

        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function wordXml(string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>{$body}<w:sectPr/></w:body></w:document>
XML;
    }
}
