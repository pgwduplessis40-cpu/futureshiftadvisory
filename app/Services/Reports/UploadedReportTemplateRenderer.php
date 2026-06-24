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

    public function renderStandaloneFragmentFromBytes(string $bytes): ?string
    {
        try {
            $parts = $this->docxHtmlParts($bytes);
        } catch (Throwable) {
            return null;
        }

        if ($parts === null) {
            return null;
        }

        $header = trim($parts['header']);
        $body = trim($parts['body']);
        $footer = trim($parts['footer']);

        if ($body === '' && $header === '' && $footer === '') {
            return null;
        }

        return sprintf(
            <<<'HTML'
<style>%s
.uploaded-docx-standalone { background: #fff; color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; }
.uploaded-docx-standalone .docx-template-footer { color: #4b5563; font-size: 10px; margin-top: 18mm; }
</style>
<div class="uploaded-docx-standalone">
%s
<div class="uploaded-docx-report-template">
%s
</div>
%s
</div>
HTML,
            $this->docxCss(),
            $header === '' ? '' : '<header class="docx-template-header">'.$header.'</header>',
            $body,
            $footer === '' ? '' : '<footer class="docx-template-footer">'.$footer.'</footer>',
        );
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public function render(Report $report, Template $template, string $sections, array $tokens, string $css): ?string
    {
        return $this->renderDocument($report->title, $template, $sections, $tokens, $css);
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public function renderDocument(string $title, Template $template, string $sections, array $tokens, string $css): ?string
    {
        if (! $this->supports($template)) {
            return null;
        }

        $path = data_get($template->structure, 'uploaded_file.stored_path');
        if (! is_string($path) || trim($path) === '' || ! Storage::disk('secure_local')->exists($path)) {
            return null;
        }

        try {
            $parts = $this->docxHtmlParts(Storage::disk('secure_local')->get($path));
        } catch (Throwable) {
            return null;
        }

        if ($parts === null) {
            return null;
        }

        $templateHtml = implode("\n", $parts);
        $hasSectionsToken = $this->containsSectionsToken($templateHtml);
        $header = strtr($parts['header'], $tokens);
        $body = strtr($parts['body'], $tokens);
        $footer = strtr($parts['footer'], $tokens);
        $pdfFooter = $this->pdfFooterTemplate($footer);

        if (! $hasSectionsToken) {
            $body .= "\n".'<div class="docx-page-break"></div><main class="report-content">'.$sections.'</main>';
        }

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<title>%s</title>
%s
<style>%s
%s</style>
</head>
<body data-report-template="%s" data-report-template-source="uploaded-docx">
%s
<div class="uploaded-docx-report-template">
%s
</div>
</body>
</html>
HTML,
            $this->escape($title),
            $pdfFooter,
            $css,
            $this->docxCss(),
            $this->escape((string) $template->getKey()),
            $header === '' ? '' : '<header class="docx-template-header">'.$header.'</header>',
            $body,
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

    /**
     * @return array{header:string,body:string,footer:string}|null
     */
    private function docxHtmlParts(string $bytes): ?array
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

            $parts = $this->documentXmlParts($zip);
            $zip->close();
        } finally {
            @unlink($path);
        }

        $html = [
            'header' => trim(implode("\n", array_map(fn (string $xml): string => $this->xmlPartHtml($xml), $parts['headers']))),
            'body' => $this->xmlPartHtml($parts['document']),
            'footer' => trim(implode("\n", array_map(fn (string $xml): string => $this->xmlPartHtml($xml), $parts['footers']))),
        ];

        return trim(implode('', $html)) === '' ? null : $html;
    }

    /**
     * @return array{headers:array<int, string>,document:string,footers:array<int, string>}
     */
    private function documentXmlParts(ZipArchive $zip): array
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
            'headers' => array_values($headers),
            'document' => is_string($document) ? $document : '',
            'footers' => array_values($footers),
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
        $css = $this->paragraphCss($paragraph);

        if ($trimmed === '') {
            return $this->emptyParagraphHtml($paragraph, $css);
        }

        if ($this->isSectionsToken($trimmed)) {
            return $trimmed;
        }

        $tag = $this->paragraphTag($paragraph);
        $content = nl2br($this->escape($text));

        return sprintf(
            '<%1$s class="docx-template-block" style="%2$s">%3$s</%1$s>',
            $tag,
            $this->escape($css),
            $content,
        );
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
            str_contains($style, 'heading1'),
            str_contains($style, 'heading 1') => 'h1',
            str_contains($style, 'heading2'),
            str_contains($style, 'heading 2') => 'h2',
            str_contains($style, 'heading3'),
            str_contains($style, 'heading 3') => 'h3',
            default => 'p',
        };
    }

    private function emptyParagraphHtml(DOMElement $paragraph, string $css): string
    {
        if ($this->paragraphBorderCss($paragraph) === '') {
            return '';
        }

        return sprintf(
            '<div class="docx-template-rule" style="%s"></div>',
            $this->escape($css),
        );
    }

    private function paragraphCss(DOMElement $paragraph): string
    {
        $css = [];
        $properties = $this->firstChild($paragraph, 'pPr');
        if ($properties instanceof DOMElement) {
            $alignment = $this->firstChild($properties, 'jc')?->getAttributeNS(self::WORD_NAMESPACE, 'val');
            if (is_string($alignment) && $alignment !== '') {
                $css[] = 'text-align: '.$this->cssAlignment($alignment).';';
            }

            $spacing = $this->firstChild($properties, 'spacing');
            if ($spacing instanceof DOMElement) {
                $before = $this->twipsAttributeToPt($spacing, 'before');
                $after = $this->twipsAttributeToPt($spacing, 'after');

                if ($before !== null) {
                    $css[] = 'margin-top: '.$before.'pt;';
                }

                if ($after !== null) {
                    $css[] = 'margin-bottom: '.$after.'pt;';
                }
            }

            $indent = $this->firstChild($properties, 'ind');
            if ($indent instanceof DOMElement) {
                $left = $this->twipsAttributeToPt($indent, 'left');
                if ($left !== null) {
                    $css[] = 'margin-left: '.$left.'pt;';
                }
            }

            $border = $this->paragraphBorderCss($paragraph);
            if ($border !== '') {
                $css[] = $border;
            }
        }

        $css[] = $this->firstRunCss($paragraph);

        return trim(implode(' ', array_filter($css)));
    }

    private function paragraphBorderCss(DOMElement $paragraph): string
    {
        $properties = $this->firstChild($paragraph, 'pPr');
        $borders = $properties instanceof DOMElement ? $this->firstChild($properties, 'pBdr') : null;

        if (! $borders instanceof DOMElement) {
            return '';
        }

        $css = [];
        foreach ($borders->childNodes as $border) {
            if (! $border instanceof DOMElement) {
                continue;
            }

            $side = match ($border->localName) {
                'top' => 'top',
                'bottom' => 'bottom',
                'left' => 'left',
                'right' => 'right',
                default => null,
            };

            if ($side === null) {
                continue;
            }

            $css[] = sprintf('border-%s: %s;', $side, $this->borderCss($border));
        }

        return implode(' ', $css);
    }

    private function tableHtml(DOMElement $table): string
    {
        $tableStyle = $this->tableCss($table);
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
                $cells .= sprintf(
                    '<td style="%s">%s</td>',
                    $this->escape($this->cellCss($cell)),
                    $cellHtml === '' ? '&nbsp;' : $cellHtml,
                );
            }

            if ($cells !== '') {
                $rows .= '<tr>'.$cells.'</tr>';
            }
        }

        return $rows === '' ? '' : sprintf(
            '<table class="docx-template-table" style="%s">%s</table>',
            $this->escape($tableStyle),
            $rows,
        );
    }

    private function tableCss(DOMElement $table): string
    {
        $css = [];
        $properties = $this->firstChild($table, 'tblPr');
        if (! $properties instanceof DOMElement) {
            return '';
        }

        $width = $this->firstChild($properties, 'tblW');
        if ($width instanceof DOMElement) {
            $css[] = $this->widthCss($width);
        }

        $alignment = $this->firstChild($properties, 'jc')?->getAttributeNS(self::WORD_NAMESPACE, 'val');
        if (is_string($alignment) && $alignment !== '') {
            $css[] = $this->tableAlignmentCss($alignment);
        }

        $borders = $this->firstChild($properties, 'tblBorders');
        if ($borders instanceof DOMElement) {
            $top = $this->firstChild($borders, 'top');
            if ($top instanceof DOMElement) {
                $css[] = 'border: '.$this->borderCss($top).';';
            }
        }

        return trim(implode(' ', array_filter($css)));
    }

    private function cellCss(DOMElement $cell): string
    {
        $css = [];
        $properties = $this->firstChild($cell, 'tcPr');
        if (! $properties instanceof DOMElement) {
            return '';
        }

        $width = $this->firstChild($properties, 'tcW');
        if ($width instanceof DOMElement) {
            $css[] = $this->widthCss($width);
        }

        $shading = $this->firstChild($properties, 'shd');
        if ($shading instanceof DOMElement) {
            $fill = $this->hexColor($shading->getAttributeNS(self::WORD_NAMESPACE, 'fill'));
            if ($fill !== null) {
                $css[] = 'background-color: '.$fill.';';
            }
        }

        $verticalAlign = $this->firstChild($properties, 'vAlign')?->getAttributeNS(self::WORD_NAMESPACE, 'val');
        if (is_string($verticalAlign) && $verticalAlign !== '') {
            $css[] = 'vertical-align: '.($verticalAlign === 'center' ? 'middle' : $verticalAlign).';';
        }

        return trim(implode(' ', array_filter($css)));
    }

    private function widthCss(DOMElement $width): string
    {
        $value = $width->getAttributeNS(self::WORD_NAMESPACE, 'w');
        $type = $width->getAttributeNS(self::WORD_NAMESPACE, 'type');

        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        if ($type === 'pct') {
            return 'width: '.round(((float) $value) / 50, 2).'%;';
        }

        $twips = (float) $value;
        if ($twips >= 9000) {
            return 'width: 100%;';
        }

        return 'width: '.round(($twips / 9360) * 100, 2).'%;';
    }

    private function firstRunCss(DOMElement $paragraph): string
    {
        foreach ($paragraph->getElementsByTagNameNS(self::WORD_NAMESPACE, 'r') as $run) {
            if (! $run instanceof DOMElement || trim($this->nodeText($run)) === '') {
                continue;
            }

            $properties = $this->firstChild($run, 'rPr');
            if (! $properties instanceof DOMElement) {
                return '';
            }

            $css = [];
            if ($this->firstChild($properties, 'b') instanceof DOMElement) {
                $css[] = 'font-weight: 700;';
            }

            if ($this->firstChild($properties, 'i') instanceof DOMElement) {
                $css[] = 'font-style: italic;';
            }

            if ($this->firstChild($properties, 'caps') instanceof DOMElement) {
                $css[] = 'text-transform: uppercase;';
            }

            $color = $this->firstChild($properties, 'color');
            if ($color instanceof DOMElement) {
                $hex = $this->hexColor($color->getAttributeNS(self::WORD_NAMESPACE, 'val'));
                if ($hex !== null) {
                    $css[] = 'color: '.$hex.';';
                }
            }

            $size = $this->firstChild($properties, 'sz');
            if ($size instanceof DOMElement) {
                $value = $size->getAttributeNS(self::WORD_NAMESPACE, 'val');
                if (is_numeric($value)) {
                    $css[] = 'font-size: '.round(((float) $value) / 2, 2).'pt;';
                }
            }

            return trim(implode(' ', $css));
        }

        return '';
    }

    private function firstChild(DOMElement $element, string $localName): ?DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::WORD_NAMESPACE && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function borderCss(DOMElement $border): string
    {
        $value = $border->getAttributeNS(self::WORD_NAMESPACE, 'val');
        if (in_array($value, ['nil', 'none'], true)) {
            return '0 none transparent';
        }

        $size = $border->getAttributeNS(self::WORD_NAMESPACE, 'sz');
        $width = is_numeric($size) ? max(1, round(((float) $size) / 8, 2)) : 1;
        $color = $this->hexColor($border->getAttributeNS(self::WORD_NAMESPACE, 'color')) ?? '#d7e2dd';

        return $width.'px solid '.$color;
    }

    private function twipsAttributeToPt(DOMElement $element, string $attribute): ?string
    {
        $value = $element->getAttributeNS(self::WORD_NAMESPACE, $attribute);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (string) round(((float) $value) / 20, 2);
    }

    private function cssAlignment(string $alignment): string
    {
        return match ($alignment) {
            'center' => 'center',
            'right' => 'right',
            'both' => 'justify',
            default => 'left',
        };
    }

    private function tableAlignmentCss(string $alignment): string
    {
        return match ($alignment) {
            'center' => 'margin-left: auto; margin-right: auto;',
            'right' => 'margin-left: auto;',
            default => '',
        };
    }

    private function hexColor(string $value): ?string
    {
        if ($value === '' || Str::lower($value) === 'auto' || ! preg_match('/^[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return '#'.strtoupper($value);
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

    private function pdfFooterTemplate(string $footer): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($footer), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\bPage\s+\d+\b/i', 'Page [[PDF_PAGE_NUMBER]]', $text) ?? $text;
        if (! str_contains($text, '[[PDF_PAGE_NUMBER]]')) {
            $text .= ' - Page [[PDF_PAGE_NUMBER]]';
        }

        $text = str_replace(
            '[[PDF_PAGE_NUMBER]]',
            '<span class="pageNumber"></span>',
            $this->escape($text),
        );

        return sprintf(
            <<<'HTML'
<template data-pdf-footer>
<style>
* { box-sizing: border-box; }
.pdf-footer { color: #9FB0C8; font-family: Arial, sans-serif; font-size: 7.5px; line-height: 1.35; padding: 0 16mm; width: 100%%; }
.pdf-footer-rule { border-top: 1px solid #B8860B; height: 0; margin: 0 0 3mm; }
.pdf-footer-bar { background: #1C2B45; min-height: 11mm; padding: 3mm 3mm 2.5mm; width: 100%%; }
</style>
<div class="pdf-footer">
<div class="pdf-footer-rule"></div>
<div class="pdf-footer-bar">%s</div>
</div>
</template>
HTML,
            $text,
        );
    }

    private function docxCss(): string
    {
        return <<<'CSS'
@page { size: A4; margin: 18mm 25.4mm 18mm; }
.docx-template-header { margin-bottom: 8mm; }
.docx-template-header .docx-template-table { margin: 0; }
template[data-pdf-footer] { display: none; }
.uploaded-docx-report-template { background: #fff; }
.docx-template-block { margin: 0 0 10px; }
h1.docx-template-block { font-size: 25px; line-height: 1.15; margin-bottom: 14px; }
h2.docx-template-block { font-size: 17px; line-height: 1.25; margin: 16px 0 9px; }
h3.docx-template-block { font-size: 13px; line-height: 1.3; margin: 12px 0 7px; }
.docx-template-rule { height: 1px; margin: 8px 0; }
.docx-template-table { border-collapse: collapse; margin: 10px 0 14px; table-layout: fixed; width: 100%; }
.docx-template-table td { border: 1px solid #d7e2dd; padding: 7px 8px; vertical-align: top; }
.docx-template-table .docx-template-block { margin-bottom: 5px; }
.docx-page-break { break-before: page; height: 0; page-break-before: always; }
CSS;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
