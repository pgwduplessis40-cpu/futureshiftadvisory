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
            $pages[] = $current;
        }

        return $this->pdf($pages === [] ? [[]] : $pages);
    }

    /**
     * @param  array<int, array<int, array{text:string,size:int,leading:int}>>  $pages
     */
    private function pdf(array $pages): string
    {
        $objectCount = 2 + (count($pages) * 2) + 1;
        $fontObjectId = $objectCount;
        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        foreach ($pages as $index => $lines) {
            $pageObjectId = 3 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = "{$pageObjectId} 0 R";

            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $fontObjectId,
                $contentObjectId,
            );
            $objects[$contentObjectId] = $this->stream($this->content($lines));
        }

        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($pages));
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
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
     * @param  array<int, array{text:string,size:int,leading:int}>  $lines
     */
    private function content(array $lines): string
    {
        $content = "BT\n";
        $content .= '/F1 16 Tf '.self::MARGIN.' '.(self::PAGE_HEIGHT - self::MARGIN)." Td\n";
        $currentSize = 16;

        foreach ($lines as $index => $line) {
            if ($line['size'] !== $currentSize) {
                $currentSize = $line['size'];
                $content .= "/F1 {$currentSize} Tf\n";
            }

            if ($index > 0) {
                $content .= "0 -{$line['leading']} Td\n";
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
     * @param  array<int, array<int, array{text:string,size:int,leading:int}>>  $pages
     * @param  array<int, array{text:string,size:int,leading:int}>  $current
     */
    private function addLine(array &$pages, array &$current, int &$y, string $text, int $size, int $leading): void
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
        ];
        $y -= $leading;
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text): array
    {
        return explode("\n", wordwrap($text, 94, "\n", true));
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
