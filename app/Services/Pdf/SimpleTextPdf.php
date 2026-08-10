<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Illuminate\Support\Str;

final class SimpleTextPdf
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const MARGIN = 50;

    private const CONTENT_BOTTOM = 58;

    private const ACCENT = [13, 122, 122];

    private const NAVY = [28, 47, 74];

    private const GOLD = [184, 134, 11];

    private const MUTED = [96, 109, 128];

    private const PAPER = [248, 245, 238];

    /**
     * @param  array<int, string>  $paragraphs
     */
    public function render(string $title, array $paragraphs): string
    {
        $pages = [];
        $current = [];
        $y = self::PAGE_HEIGHT - self::MARGIN;

        $this->addLine($pages, $current, $y, $title, 16, 22, 'F2');
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

        if ($this->pageHasVisibleContent($current)) {
            $pages[] = $current;
        }

        return $this->pdf($pages === [] ? [[]] : $pages);
    }

    /**
     * @param  array<int, array{type?:string,text?:string,items?:array<int, string>,headers?:array<int, string>,rows?:array<int, array<int, string>>,widths?:array<int, float|int>}>  $blocks
     */
    public function renderStructured(string $title, array $blocks): string
    {
        $pages = [];
        $current = [];
        $meta = [];

        while ($blocks !== []) {
            $first = $blocks[0];
            $type = (string) ($first['type'] ?? 'paragraph');

            if ($type === 'meta') {
                $meta[] = (string) ($first['text'] ?? '');
                array_shift($blocks);

                continue;
            }

            if ($type === 'spacer') {
                array_shift($blocks);
            }

            break;
        }

        $y = $this->startReportPage($current, $title, $meta);

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'spacer') {
                $this->addLine($pages, $current, $y, '', 10, 8, reportTitle: $title);

                continue;
            }

            if ($type === 'section') {
                $this->addSectionHeading($pages, $current, $y, (string) ($block['text'] ?? ''), $title);

                continue;
            }

            if ($type === 'subsection') {
                $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 11, 15, 'F2', self::MARGIN, self::NAVY, $title);
                $this->addLine($pages, $current, $y, '', 10, 2, reportTitle: $title);

                continue;
            }

            if ($type === 'meta') {
                $this->addWrapped($pages, $current, $y, (string) ($block['text'] ?? ''), 9, 12, 'F1', self::MARGIN, self::MUTED, $title);

                continue;
            }

            if ($type === 'bullets') {
                $this->addBullets($pages, $current, $y, (array) ($block['items'] ?? []), $title);

                continue;
            }

            if ($type === 'table') {
                $this->addTable(
                    $pages,
                    $current,
                    $y,
                    (array) ($block['headers'] ?? []),
                    (array) ($block['rows'] ?? []),
                    (array) ($block['widths'] ?? []),
                    $title,
                );

                continue;
            }

            $this->addParagraph($pages, $current, $y, (string) ($block['text'] ?? ''), $title);
        }

        if ($this->pageHasVisibleContent($current)) {
            $pages[] = $current;
        }

        return $this->pdf($pages === [] ? [[]] : $pages);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
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
        $pageCount = count($pages);

        foreach ($pages as $index => $operations) {
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
            $objects[$contentObjectId] = $this->stream($this->content($operations, $index + 1, $pageCount));
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
     * @param  array<int, array<string, mixed>>  $operations
     */
    private function content(array $operations, int $pageNumber, int $pageCount): string
    {
        $content = '';

        foreach ($operations as $operation) {
            $kind = (string) ($operation['kind'] ?? 'text');

            if ($kind === 'rect') {
                $content .= $this->fillColor((array) ($operation['color'] ?? [255, 255, 255]));
                $content .= sprintf(
                    "%s %s %s %s re f\n",
                    $this->number((float) $operation['x']),
                    $this->number((float) $operation['y']),
                    $this->number((float) $operation['width']),
                    $this->number((float) $operation['height']),
                );

                continue;
            }

            if ($kind === 'line') {
                $content .= $this->strokeColor((array) ($operation['color'] ?? [0, 0, 0]));
                $content .= sprintf(
                    "%s w %s %s m %s %s l S\n",
                    $this->number((float) ($operation['width'] ?? 1)),
                    $this->number((float) $operation['x1']),
                    $this->number((float) $operation['y1']),
                    $this->number((float) $operation['x2']),
                    $this->number((float) $operation['y2']),
                );

                continue;
            }

            $text = (string) ($operation['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $font = (string) ($operation['font'] ?? 'F1');
            $size = (int) ($operation['size'] ?? 10);
            $x = (float) ($operation['x'] ?? self::MARGIN);
            $y = (float) ($operation['y'] ?? self::MARGIN);
            $color = (array) ($operation['color'] ?? [19, 35, 58]);

            $content .= $this->fillColor($color);
            $content .= sprintf(
                "BT /%s %d Tf %s %s Td (%s) Tj ET\n",
                $font,
                $size,
                $this->number($x),
                $this->number($y),
                $this->escape($text),
            );
        }

        $footerText = "Future Shift Advisory    |    Confidential    |    Page {$pageNumber} of {$pageCount}";
        $content .= $this->fillColor(self::MUTED);
        $content .= 'BT /F1 8 Tf '.self::MARGIN.' 34 Td ('.$this->escape($footerText).") Tj ET\n";

        return $content;
    }

    private function stream(string $content): string
    {
        return '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, string>  $meta
     */
    private function startReportPage(array &$current, string $title, array $meta): int
    {
        $current = [];
        $this->addRect($current, self::MARGIN, 792, 495, 6, self::NAVY);
        $this->addBrand($current, 50, 744);
        $this->addBadge($current, 430, 754, 'REPORT PDF');
        $this->addLineShape($current, self::MARGIN, 732, 545, 732, [216, 209, 194]);
        $this->addText($current, $title, 22, 692, 24, 'F2', self::NAVY);
        $this->addText($current, 'Prepared for lender and investor review', 50, 672, 10, 'F2', self::ACCENT);

        $y = 634;
        $columns = min(2, max(1, $meta === [] ? 1 : 2));
        $boxWidth = $columns === 1 ? 495 : 238;
        $rowHeight = 34;

        foreach (array_values($meta) as $index => $line) {
            $column = $index % $columns;
            $row = intdiv($index, $columns);
            $x = self::MARGIN + ($column * ($boxWidth + 19));
            $boxY = $y - ($row * ($rowHeight + 8));
            [$label, $value] = $this->labelValue($line);

            $this->addRect($current, $x, $boxY - 22, $boxWidth, $rowHeight, self::PAPER);
            $this->addText($current, $label, $x + 9, $boxY - 3, 7, 'F2', self::MUTED);
            $this->addText($current, $value, $x + 9, $boxY - 16, 9, 'F1', [19, 35, 58]);
        }

        $rows = (int) ceil(count($meta) / max(1, $columns));

        return $rows === 0 ? 636 : max(500, $y - ($rows * ($rowHeight + 8)) - 14);
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     */
    private function startContinuationPage(array &$current, string $title): int
    {
        $current = [];
        $this->addRect($current, self::MARGIN, 792, 495, 5, self::NAVY);
        $this->addBrand($current, 50, 754, compact: true);
        $this->addText($current, $title, 244, 764, 10, 'F2', self::NAVY);
        $this->addLineShape($current, self::MARGIN, 742, 545, 742, [216, 209, 194]);

        return 716;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function ensureSpace(array &$pages, array &$current, int &$y, int $needed, ?string $reportTitle = null): void
    {
        if ($y - $needed >= self::CONTENT_BOTTOM) {
            return;
        }

        if ($this->pageHasVisibleContent($current)) {
            $pages[] = $current;
        }

        $y = $reportTitle === null
            ? self::PAGE_HEIGHT - self::MARGIN
            : $this->startContinuationPage($current, $reportTitle);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addLine(array &$pages, array &$current, int &$y, string $text, int $size, int $leading, string $font = 'F1', int $x = self::MARGIN, array $color = [19, 35, 58], ?string $reportTitle = null): void
    {
        $this->ensureSpace($pages, $current, $y, max($leading, $size + 3), $reportTitle);

        $this->addText($current, $this->normalise($text), $x, $y, $size, $font, $color);
        $y -= $leading;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addWrapped(array &$pages, array &$current, int &$y, string $text, int $size, int $leading, string $font = 'F1', int $x = self::MARGIN, array $color = [19, 35, 58], ?string $reportTitle = null): void
    {
        $text = trim($this->normalise($text));

        if ($text === '') {
            return;
        }

        foreach ($this->wrapForWidth($text, $size, $x) as $line) {
            $this->addLine($pages, $current, $y, $line, $size, $leading, $font, $x, $color, $reportTitle);
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addSectionHeading(array &$pages, array &$current, int &$y, string $heading, string $reportTitle): void
    {
        $this->ensureSpace($pages, $current, $y, 44, $reportTitle);

        $this->addRect($current, self::MARGIN, $y - 17, 4, 26, self::ACCENT);
        $this->addLineShape($current, self::MARGIN + 12, $y - 17, 545, $y - 17, [238, 231, 219]);
        $this->addWrapped($pages, $current, $y, $heading, 15, 18, 'F2', self::MARGIN + 14, self::NAVY, $reportTitle);
        $this->addLine($pages, $current, $y, '', 10, 4, reportTitle: $reportTitle);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addParagraph(array &$pages, array &$current, int &$y, string $text, string $reportTitle): void
    {
        foreach ($this->paragraphs($text) as $paragraph) {
            $this->addWrapped($pages, $current, $y, $paragraph, 10, 13, 'F1', self::MARGIN, [25, 31, 42], $reportTitle);
            $this->addLine($pages, $current, $y, '', 10, 5, reportTitle: $reportTitle);
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, mixed>  $items
     */
    private function addBullets(array &$pages, array &$current, int &$y, array $items, string $reportTitle): void
    {
        foreach ($items as $item) {
            $this->ensureSpace($pages, $current, $y, 18, $reportTitle);
            $this->addText($current, '-', self::MARGIN + 2, $y, 10, 'F2', self::GOLD);
            $this->addWrapped($pages, $current, $y, (string) $item, 9, 12, 'F1', self::MARGIN + 14, [25, 31, 42], $reportTitle);
            $this->addLine($pages, $current, $y, '', 10, 2, reportTitle: $reportTitle);
        }

        $this->addLine($pages, $current, $y, '', 10, 4, reportTitle: $reportTitle);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, mixed>  $headers
     * @param  array<int, mixed>  $rows
     * @param  array<int, mixed>  $widthWeights
     */
    private function addTable(array &$pages, array &$current, int &$y, array $headers, array $rows, array $widthWeights, string $reportTitle): void
    {
        $headers = array_values(array_map('strval', $headers));
        $rows = array_values(array_filter($rows, 'is_array'));

        if ($headers === []) {
            return;
        }

        $columnCount = count($headers);
        $tableWidth = self::PAGE_WIDTH - (self::MARGIN * 2);
        $weights = $this->columnWeights($columnCount, $widthWeights);
        $widths = array_map(fn (float $weight): float => ($weight / array_sum($weights)) * $tableWidth, $weights);
        $xPositions = [self::MARGIN];

        for ($i = 1; $i < $columnCount; $i++) {
            $xPositions[$i] = $xPositions[$i - 1] + $widths[$i - 1];
        }

        $this->ensureSpace($pages, $current, $y, 30, $reportTitle);
        $this->addRect($current, self::MARGIN, $y - 14, $tableWidth, 21, self::PAPER);

        foreach ($headers as $index => $header) {
            $this->addText($current, $header, $xPositions[$index] + 4, $y - 6, 7, 'F2', self::NAVY);
        }

        $y -= 24;

        foreach ($rows as $row) {
            $row = array_values(array_map('strval', $row));
            $wrappedCells = [];
            $maxLines = 1;

            for ($i = 0; $i < $columnCount; $i++) {
                $cell = $row[$i] ?? '';
                $wrappedCells[$i] = $this->wrapForCharacters($cell, max(10, (int) floor($widths[$i] / 4.8)));
                $maxLines = max($maxLines, count($wrappedCells[$i]));
            }

            $rowHeight = max(18, 9 + ($maxLines * 10));
            $this->ensureSpace($pages, $current, $y, $rowHeight + 6, $reportTitle);
            $this->addLineShape($current, self::MARGIN, $y + 4, 545, $y + 4, [228, 232, 226]);

            for ($i = 0; $i < $columnCount; $i++) {
                foreach ($wrappedCells[$i] as $lineIndex => $line) {
                    $this->addText($current, $line, $xPositions[$i] + 4, $y - ($lineIndex * 10), 7, 'F1', [25, 31, 42]);
                }
            }

            $y -= $rowHeight;
        }

        $this->addLine($pages, $current, $y, '', 10, 8, reportTitle: $reportTitle);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $weights
     * @return array<int, float>
     */
    private function columnWeights(int $columns, array $weights): array
    {
        $values = array_values(array_map('floatval', $weights));

        if (count($values) !== $columns || array_sum($values) <= 0) {
            return array_fill(0, $columns, 1.0);
        }

        return $values;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addBrand(array &$current, int $x, int $y, bool $compact = false): void
    {
        $this->addRect($current, $x, $y - 2, 7, 13, [95, 151, 135]);
        $this->addRect($current, $x + 10, $y - 2, 7, 22, [40, 134, 121]);
        $this->addRect($current, $x + 20, $y - 2, 7, 31, self::ACCENT);
        $this->addText($current, 'Future Shift', $x + 38, $y + ($compact ? 9 : 14), $compact ? 11 : 13, 'F2', self::NAVY);
        $this->addText($current, 'ADVISORY', $x + 38, $y + ($compact ? -2 : 1), $compact ? 6 : 7, 'F2', [90, 122, 112]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addBadge(array &$current, int $x, int $y, string $text): void
    {
        $this->addRect($current, $x, $y - 10, 115, 24, self::PAPER);
        $this->addText($current, $text, $x + 16, $y - 1, 8, 'F2', self::NAVY);
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, int>  $color
     */
    private function addText(array &$current, string $text, float $x, float $y, int $size, string $font = 'F1', array $color = [19, 35, 58]): void
    {
        $current[] = [
            'kind' => 'text',
            'text' => $this->normalise($text),
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'font' => $font,
            'color' => $color,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, int>  $color
     */
    private function addRect(array &$current, float $x, float $y, float $width, float $height, array $color): void
    {
        $current[] = [
            'kind' => 'rect',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'color' => $color,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, int>  $color
     */
    private function addLineShape(array &$current, float $x1, float $y1, float $x2, float $y2, array $color, float $width = 0.8): void
    {
        $current[] = [
            'kind' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'width' => $width,
            'color' => $color,
        ];
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

        return $this->wrapForCharacters($text, $characters);
    }

    /**
     * @return array<int, string>
     */
    private function wrapForCharacters(string $text, int $characters): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [] as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            foreach (explode("\n", wordwrap($segment, max(8, $characters), "\n", true)) as $line) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @return array<int, string>
     */
    private function paragraphs(string $text): array
    {
        $paragraphs = collect(preg_split('/\R{2,}/', trim($this->normalise($text))) ?: [])
            ->flatMap(fn (string $paragraph): array => $this->splitLongParagraph($paragraph))
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->values()
            ->all();

        return $paragraphs === [] ? [] : $paragraphs;
    }

    /**
     * @return array<int, string>
     */
    private function splitLongParagraph(string $paragraph): array
    {
        if (strlen($paragraph) < 900) {
            return [$paragraph];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph] as $sentence) {
            if ($current !== '' && strlen($current.' '.$sentence) > 700) {
                $chunks[] = $current;
                $current = $sentence;

                continue;
            }

            $current = trim($current.' '.$sentence);
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks === [] ? [$paragraph] : $chunks;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function labelValue(string $line): array
    {
        if (str_contains($line, ':')) {
            [$label, $value] = explode(':', $line, 2);

            return [trim($label), trim($value)];
        }

        return ['Detail', trim($line)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    private function pageHasVisibleContent(array $operations): bool
    {
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? 'text') !== 'text') {
                return true;
            }

            if (($operation['text'] ?? '') !== '') {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array<int, mixed>  $color
     */
    private function fillColor(array $color): string
    {
        return sprintf(
            "%s %s %s rg\n",
            $this->number(((float) ($color[0] ?? 0)) / 255),
            $this->number(((float) ($color[1] ?? 0)) / 255),
            $this->number(((float) ($color[2] ?? 0)) / 255),
        );
    }

    /**
     * @param  array<int, mixed>  $color
     */
    private function strokeColor(array $color): string
    {
        return sprintf(
            "%s %s %s RG\n",
            $this->number(((float) ($color[0] ?? 0)) / 255),
            $this->number(((float) ($color[1] ?? 0)) / 255),
            $this->number(((float) ($color[2] ?? 0)) / 255),
        );
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }
}
