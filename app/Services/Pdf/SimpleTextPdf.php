<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Illuminate\Support\Str;

final class SimpleTextPdf
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const MARGIN = 50;

    /**
     * @param  array<int, string>  $paragraphs
     */
    public function render(string $title, array $paragraphs): string
    {
        $pages = [];
        $current = [];
        $y = self::PAGE_HEIGHT - self::MARGIN;

        $this->addLine($pages, $current, $y, $title, 16, 22);
        $this->addLine($pages, $current, $y, '', 10, 14);

        foreach ($paragraphs as $paragraph) {
            $text = trim($this->normalise($paragraph));

            if ($text === '') {
                $this->addLine($pages, $current, $y, '', 10, 14);

                continue;
            }

            foreach ($this->wrap($text) as $line) {
                $this->addLine($pages, $current, $y, $line, 10, 14);
            }

            $this->addLine($pages, $current, $y, '', 10, 8);
        }

        if ($current !== []) {
            foreach ($current as $line) {
                if ($line['text'] !== '') {
                    $pages[] = $current;

                    break;
                }
            }
        }

        return $this->pdf($pages === [] ? [[]] : $pages);
    }

    /**
     * @param  array<int, array{type?:string,text?:string,items?:array<int, string>}>  $blocks
     */
    public function renderStructured(string $title, array $blocks): string
    {
        $pages = [];
        $current = [];
        $y = self::PAGE_HEIGHT - self::MARGIN;

        $this->addWrapped($pages, $current, $y, $title, 20, 26, 'F2');
        $this->addLine($pages, $current, $y, '', 10, 10);

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'spacer') {
                $this->addLine($pages, $current, $y, '', 10, 8);

                continue;
            }

            if ($type === 'section') {
                if ($current !== []) {
                    $this->addLine($pages, $current, $y, '', 10, 8);
                }

                $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 14, 18, 'F2');

                continue;
            }

            if ($type === 'subsection') {
                $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 11, 15, 'F2');

                continue;
            }

            if ($type === 'meta') {
                $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 9, 12, 'F1');

                continue;
            }

            if ($type === 'bullets') {
                foreach ((array) ($block['items'] ?? []) as $item) {
                    $this->addWrapped($pages, $current, $y, '- '.$item, 10, 14, 'F1', self::MARGIN + 12);
                }

                $this->addLine($pages, $current, $y, '', 10, 6);

                continue;
            }

            $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 10, 14);
            $this->addLine($pages, $current, $y, '', 10, 5);
        }

        if ($current !== []) {
            foreach ($current as $line) {
                if ($line['text'] !== '') {
                    $pages[] = $current;

                    break;
                }
            }
        }

        return $this->pdf($pages === [] ? [[]] : $pages);
    }

    /**
     * @param  array<int, array<int, array{text:string,size:int,leading:int}>>  $pages
     */
    private function pdf(array $pages): string
    {
        $objectCount = 2 + (count($pages) * 2) + 2;
        $fontObjectId = $objectCount - 1;
        $boldFontObjectId = $objectCount;
        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        foreach ($pages as $index => $lines) {
            $pageObjectId = 3 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = "{$pageObjectId} 0 R";

            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $fontObjectId,
                $boldFontObjectId,
                $contentObjectId,
            );
            $objects[$contentObjectId] = $this->stream($this->content($lines));
        }

        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($pages));
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($objectCount + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $objectCount; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n<< /Size ".($objectCount + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * @param  array<int, array{text:string,size:int,leading:int,font?:string,x?:int}>  $lines
     */
    private function content(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $content = "BT\n";
        $first = $lines[0];
        $currentSize = (int) $first['size'];
        $currentFont = (string) ($first['font'] ?? 'F1');
        $currentX = (int) ($first['x'] ?? self::MARGIN);
        $content .= "/{$currentFont} {$currentSize} Tf {$currentX} ".(self::PAGE_HEIGHT - self::MARGIN)." Td\n";

        foreach ($lines as $index => $line) {
            $font = (string) ($line['font'] ?? 'F1');
            if ($font !== $currentFont) {
                $currentFont = $font;
                $content .= "/{$currentFont} {$currentSize} Tf\n";
            }

            if ((int) $line['size'] !== $currentSize) {
                $currentSize = $line['size'];
                $content .= "/{$currentFont} {$currentSize} Tf\n";
            }

            if ($index > 0) {
                $x = (int) ($line['x'] ?? self::MARGIN);
                $content .= ($x - $currentX).' -'.$line['leading']." Td\n";
                $currentX = $x;
            }

            if ($line['text'] !== '') {
                $content .= '('.$this->escape($line['text']).") Tj\n";
            }
        }

        return $content."ET\n";
    }

    private function stream(string $content): string
    {
        return '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
    }

    /**
     * @param  array<int, array<int, array{text:string,size:int,leading:int,font?:string,x?:int}>>  $pages
     * @param  array<int, array{text:string,size:int,leading:int,font?:string,x?:int}>  $current
     */
    private function addLine(array &$pages, array &$current, int &$y, string $text, int $size, int $leading, string $font = 'F1', int $x = self::MARGIN): void
    {
        if ($y < self::MARGIN) {
            $pages[] = $current;
            $current = [];
            $y = self::PAGE_HEIGHT - self::MARGIN;
        }

        $current[] = [
            'text' => $this->normalise($text),
            'size' => $size,
            'leading' => $current === [] ? 0 : $leading,
            'font' => $font,
            'x' => $x,
        ];
        $y -= $leading;
    }

    /**
     * @param  array<int, array<int, array{text:string,size:int,leading:int,font?:string,x?:int}>>  $pages
     * @param  array<int, array{text:string,size:int,leading:int,font?:string,x?:int}>  $current
     */
    private function addWrapped(array &$pages, array &$current, int &$y, string $text, int $size, int $leading, string $font = 'F1', int $x = self::MARGIN): void
    {
        $text = trim($this->normalise($text));

        if ($text === '') {
            return;
        }

        foreach ($this->wrapForWidth($text, $size, $x) as $line) {
            $this->addLine($pages, $current, $y, $line, $size, $leading, $font, $x);
        }
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text): array
    {
        return explode("\n", wordwrap($text, 94, "\n", true));
    }

    /**
     * @return array<int, string>
     */
    private function wrapForWidth(string $text, int $size, int $x): array
    {
        $availableWidth = max(120, self::PAGE_WIDTH - self::MARGIN - $x);
        $averageCharacterWidth = max(4.0, $size * 0.52);
        $characters = max(28, (int) floor($availableWidth / $averageCharacterWidth));

        return explode("\n", wordwrap($text, $characters, "\n", true));
    }

    private function normalise(string $text): string
    {
        $ascii = Str::ascii($text);

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $ascii) ?? '';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
