<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\Template;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

final class UploadedReportTemplateRenderer
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @param  array<string, string>  $tokens
     */
    public function render(Report $report, Template $template, string $sections, array $tokens, string $css): ?string
    {
        if (! $this->supports($template)) {
            return null;
        }

        $path = data_get($template->structure, 'uploaded_file.stored_path');
        if (! is_string($path) || trim($path) === '' || ! Storage::disk('secure_local')->exists($path)) {
            return null;
        }

        try {
            $body = $this->docxHtml(Storage::disk('secure_local')->get($path));
        } catch (Throwable) {
            return null;
        }

        if ($body === null) {
            return null;
        }

        $hasSectionsToken = $this->containsSectionsToken($body);
        $rendered = strtr($body, $tokens);

        if (! $hasSectionsToken) {
            $rendered .= "\n".'<main class="report-content">'.$sections.'</main>';
        }

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>%s
%s</style>
</head>
<body data-report-template="%s" data-report-template-source="uploaded-docx">
<div class="uploaded-docx-report-template">
%s
</div>
</body>
</html>
HTML,
            $this->escape($report->title),
            $css,
            $this->docxCss(),
            $this->escape((string) $template->getKey()),
            $rendered,
        );
    }

    public function supports(Template $template): bool
    {
        if (data_get($template->structure, 'source_kind') !== 'uploaded_file') {
            return false;
        }

        $extension = Str::lower((string) data_get($template->structure, 'uploaded_file.extension'));
        $mimeType = Str::lower((string) data_get($template->structure, 'uploaded_file.mime_type'));
        $originalName = Str::lower((string) data_get($template->structure, 'uploaded_file.original_name'));

        return $extension === 'docx'
            || str_contains($mimeType, 'wordprocessingml.document')
            || str_ends_with($originalName, '.docx');
    }

    private function docxHtml(string $bytes): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'fsa-report-template-');
        if (! is_string($path)) {
            return null;
        }

        file_put_contents($path, $bytes);

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                return null;
            }

            $parts = $this->documentParts($zip);
            $zip->close();
        } finally {
            @unlink($path);
        }

        $html = trim(implode("\n", array_filter(array_map(
            fn (string $xml): string => $this->xmlPartHtml($xml),
            $parts,
        ))));

        return $html === '' ? null : $html;
    }

    /**
     * @return array<int, string>
     */
    private function documentParts(ZipArchive $zip): array
    {
        $headers = [];
        $footers = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name)) {
                continue;
            }

            $content = $zip->getFromIndex($index);
            if (! is_string($content)) {
                continue;
            }

            if (preg_match('/^word\/header\d+\.xml$/', $name) === 1) {
                $headers[$name] = $content;
            }

            if (preg_match('/^word\/footer\d+\.xml$/', $name) === 1) {
                $footers[$name] = $content;
            }
        }

        ksort($headers);
        ksort($footers);

        $document = $zip->getFromName('word/document.xml');

        return [
            ...array_values($headers),
            is_string($document) ? $document : '',
            ...array_values($footers),
        ];
    }

    private function xmlPartHtml(string $xml): string
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if ($dom->loadXML($xml) !== true) {
                return '';
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $body = null;
        $bodies = $dom->getElementsByTagNameNS(self::WORD_NAMESPACE, 'body');
        if ($bodies->length > 0) {
            $body = $bodies->item(0);
        }

        return $this->childrenHtml($body instanceof DOMNode ? $body : $dom->documentElement);
    }

    private function childrenHtml(?DOMNode $node): string
    {
        if (! $node instanceof DOMNode) {
            return '';
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $html .= match ($child->localName) {
                'p' => $this->paragraphHtml($child),
                'tbl' => $this->tableHtml($child),
                'sectPr' => '',
                default => $this->childrenHtml($child),
            };
        }

        return $html;
    }

    private function paragraphHtml(DOMElement $paragraph): string
    {
        $text = $this->nodeText($paragraph);
        $trimmed = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($trimmed === '') {
            return '';
        }

        if ($this->isSectionsToken($trimmed)) {
            return $trimmed;
        }

        $tag = $this->paragraphTag($paragraph);
        $content = nl2br($this->escape($text));

        return sprintf('<%1$s class="docx-template-block">%2$s</%1$s>', $tag, $content);
    }

    private function paragraphTag(DOMElement $paragraph): string
    {
        $style = '';
        $styles = $paragraph->getElementsByTagNameNS(self::WORD_NAMESPACE, 'pStyle');
        if ($styles->length > 0) {
            $style = Str::lower((string) $styles->item(0)?->getAttributeNS(self::WORD_NAMESPACE, 'val'));
        }

        return match (true) {
            str_contains($style, 'title'),
            str_contains($style, 'heading1') => 'h1',
            str_contains($style, 'heading2') => 'h2',
            str_contains($style, 'heading3') => 'h3',
            default => 'p',
        };
    }

    private function tableHtml(DOMElement $table): string
    {
        $rows = '';
        foreach ($table->getElementsByTagNameNS(self::WORD_NAMESPACE, 'tr') as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = '';
            foreach ($row->getElementsByTagNameNS(self::WORD_NAMESPACE, 'tc') as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $cellHtml = $this->childrenHtml($cell);
                $cells .= '<td>'.($cellHtml === '' ? '&nbsp;' : $cellHtml).'</td>';
            }

            if ($cells !== '') {
                $rows .= '<tr>'.$cells.'</tr>';
            }
        }

        return $rows === '' ? '' : '<table class="docx-template-table">'.$rows.'</table>';
    }

    private function nodeText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::WORD_NAMESPACE && $child->localName === 't') {
                $text .= $child->textContent;

                continue;
            }

            if ($child->namespaceURI === self::WORD_NAMESPACE && $child->localName === 'tab') {
                $text .= ' ';

                continue;
            }

            if ($child->namespaceURI === self::WORD_NAMESPACE && in_array($child->localName, ['br', 'cr'], true)) {
                $text .= "\n";

                continue;
            }

            $text .= $this->nodeText($child);
        }

        return $text;
    }

    private function containsSectionsToken(string $value): bool
    {
        return Str::contains($value, [
            '{{ sections }}',
            '{{sections}}',
            '{{{ sections }}}',
            '{{{sections}}}',
        ]);
    }

    private function isSectionsToken(string $value): bool
    {
        return in_array($value, [
            '{{ sections }}',
            '{{sections}}',
            '{{{ sections }}}',
            '{{{sections}}}',
        ], true);
    }

    private function docxCss(): string
    {
        return <<<'CSS'
.uploaded-docx-report-template { background: #fff; }
.docx-template-block { margin: 0 0 10px; }
h1.docx-template-block { font-size: 25px; line-height: 1.15; margin-bottom: 14px; }
h2.docx-template-block { font-size: 17px; line-height: 1.25; margin: 16px 0 9px; }
h3.docx-template-block { font-size: 13px; line-height: 1.3; margin: 12px 0 7px; }
.docx-template-table { border-collapse: collapse; margin: 10px 0 14px; width: 100%; }
.docx-template-table td { border: 1px solid #d7e2dd; padding: 7px 8px; vertical-align: top; }
.docx-template-table .docx-template-block { margin-bottom: 5px; }
CSS;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
