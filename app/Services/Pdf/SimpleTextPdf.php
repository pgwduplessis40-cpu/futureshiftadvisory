<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Illuminate\Support\Str;

final class SimpleTextPdf
{
    public const FALLBACK_MARKER = 'FSA-SIMPLE-TEXT-PDF-FALLBACK';

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
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function renderStructured(string $title, array $blocks): string
    {
        $pages = [];
        $current = [];
        $meta = [];
        $cover = null;

        if (($blocks[0]['type'] ?? null) === 'cover') {
            $cover = array_shift($blocks);
        }

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

        $y = is_array($cover)
            ? $this->startCoverPage($current, $cover)
            : $this->startReportPage($current, $title, $meta);

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'paragraph');

            if ($type === 'spacer') {
                $this->addLine($pages, $current, $y, '', 10, 8, reportTitle: $title);

                continue;
            }

            if ($type === 'page_break') {
                $this->addPageBreak($pages, $current, $y, $title);

                continue;
            }

            if ($type === 'summary_cards') {
                $this->addSummaryCards($pages, $current, $y, (array) ($block['cards'] ?? []), $title);

                continue;
            }

            if ($type === 'callout') {
                $this->addCallout(
                    $pages,
                    $current,
                    $y,
                    (string) ($block['title'] ?? ''),
                    (string) ($block['text'] ?? ''),
                    $title,
                );

                continue;
            }

            if ($type === 'toc') {
                $this->addToc($pages, $current, $y, (array) ($block['items'] ?? []), $title);

                continue;
            }

            if ($type === 'entry') {
                $this->addEntry($pages, $current, $y, $block, $title);

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

            if ($type === 'line_chart') {
                $this->addLineChart($pages, $current, $y, $block, $title);

                continue;
            }

            if ($type === 'bar_chart') {
                $this->addBarChart($pages, $current, $y, $block, $title);

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

        $pdf = "%PDF-1.4\n%".self::FALLBACK_MARKER."\n";
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
        $this->addBadge($current, 410, 754, 'REPORT PDF');
        $this->addLineShape($current, self::MARGIN, 732, 545, 732, [216, 209, 194]);
        $this->addText($current, $title, 22, 692, 24, 'F2', self::NAVY);

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

        return $rows === 0 ? 636 : $y - ($rows * ($rowHeight + 8)) - 14;
    }

    /**
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<string, mixed>  $cover
     */
    private function startCoverPage(array &$current, array $cover): int
    {
        $current = [];
        $this->addRect($current, self::MARGIN, 792, 495, 6, self::NAVY);
        $this->addBrand($current, 50, 744);
        $this->addBadge($current, 410, 754, strtoupper((string) ($cover['document_tag'] ?? 'Report')));
        $this->addLineShape($current, self::MARGIN, 732, 545, 732, [216, 209, 194]);

        $titleLines = $this->wrapForWidth((string) ($cover['title'] ?? 'Report'), 22, self::MARGIN);
        $titleY = 625;
        foreach ($titleLines as $line) {
            $this->addText($current, $line, self::MARGIN, $titleY, 22, 'F2', self::NAVY);
            $titleY -= 28;
        }

        $subtitle = trim((string) ($cover['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $this->addText($current, $subtitle, self::MARGIN, $titleY - 8, 13, 'F1', [57, 70, 90]);
        }

        return 320;
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
    private function addPageBreak(array &$pages, array &$current, int &$y, string $reportTitle): void
    {
        if ($this->pageHasVisibleContent($current)) {
            $pages[] = $current;
        }

        $y = $this->startContinuationPage($current, $reportTitle);
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
        $this->ensureSpace($pages, $current, $y, 58, $reportTitle);

        $startY = $y;
        $this->addWrapped($pages, $current, $y, $heading, 15, 18, 'F2', self::MARGIN + 14, self::NAVY, $reportTitle);
        $ruleY = $y + 4;

        $this->addRect($current, self::MARGIN, $ruleY, 4, max(26, ($startY - $ruleY) + 11), self::ACCENT);
        $this->addLineShape($current, self::MARGIN + 12, $ruleY, 545, $ruleY, [238, 231, 219]);
        $y = min($y, $ruleY - 13);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, mixed>  $cards
     */
    private function addSummaryCards(array &$pages, array &$current, int &$y, array $cards, string $reportTitle): void
    {
        $cards = array_values(array_filter($cards, 'is_array'));

        if ($cards === []) {
            return;
        }

        $cardWidth = 238;
        $cardHeight = 54;
        $gap = 19;

        for ($offset = 0; $offset < count($cards); $offset += 2) {
            $this->ensureSpace($pages, $current, $y, $cardHeight + 12, $reportTitle);
            $rowTop = $y + 2;

            foreach (array_slice($cards, $offset, 2) as $column => $card) {
                $x = self::MARGIN + ($column * ($cardWidth + $gap));
                $bottom = $rowTop - $cardHeight;
                $label = (string) ($card['label'] ?? '');
                $value = (string) ($card['value'] ?? '');
                $note = (string) ($card['note'] ?? '');

                $this->addRect($current, $x, $bottom, $cardWidth, $cardHeight, [248, 245, 238]);
                $this->addRect($current, $x, $bottom, 4, $cardHeight, self::ACCENT);
                $this->addText($current, $label, $x + 12, $rowTop - 14, 7, 'F2', self::MUTED);
                $this->addText($current, $value, $x + 12, $rowTop - 29, 12, 'F2', self::NAVY);

                if ($note !== '') {
                    $this->addText($current, $this->truncate($note, 58), $x + 12, $rowTop - 43, 7, 'F1', self::MUTED);
                }
            }

            $y -= $cardHeight + 12;
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addCallout(array &$pages, array &$current, int &$y, string $heading, string $text, string $reportTitle): void
    {
        $heading = trim($heading);
        $lines = $text === '' ? [] : $this->wrapForCharacters($this->normalise($text), 83);
        $height = 30 + (count($lines) * 11);

        $this->ensureSpace($pages, $current, $y, $height + 14, $reportTitle);

        $top = $y + 4;
        $bottom = $top - $height;
        $this->addRect($current, self::MARGIN, $bottom, 495, $height, [252, 250, 244]);
        $this->addRect($current, self::MARGIN, $bottom, 5, $height, self::GOLD);

        if ($heading !== '') {
            $this->addText($current, $heading, self::MARGIN + 15, $top - 16, 10, 'F2', self::NAVY);
        }

        $lineY = $heading === '' ? $top - 14 : $top - 30;
        foreach ($lines as $line) {
            $this->addText($current, $line, self::MARGIN + 15, $lineY, 8, 'F1', [25, 31, 42]);
            $lineY -= 11;
        }

        $y = (int) floor($bottom - 12);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, mixed>  $items
     */
    private function addToc(array &$pages, array &$current, int &$y, array $items, string $reportTitle): void
    {
        $items = array_values(array_filter($items, 'is_array'));

        if ($items === []) {
            return;
        }

        $this->addSectionHeading($pages, $current, $y, 'Reader roadmap', $reportTitle);

        foreach ($items as $index => $item) {
            $this->ensureSpace($pages, $current, $y, 34, $reportTitle);
            $rowTop = $y + 3;
            $bottom = $rowTop - 27;
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $title = (string) ($item['title'] ?? 'Plan section');
            $detail = (string) ($item['detail'] ?? '');

            $this->addLineShape($current, self::MARGIN, $rowTop, 545, $rowTop, [238, 231, 219], 0.5);
            $this->addText($current, $number, self::MARGIN + 2, $rowTop - 15, 9, 'F2', self::ACCENT);
            $this->addText($current, $this->truncate($title, 42), self::MARGIN + 36, $rowTop - 10, 10, 'F2', self::NAVY);

            if ($detail !== '') {
                $this->addText($current, $this->truncate($detail, 76), self::MARGIN + 36, $rowTop - 23, 7, 'F1', self::MUTED);
            }

            $y = (int) floor($bottom - 4);
        }

        $this->addLine($pages, $current, $y, '', 10, 4, reportTitle: $reportTitle);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<string, mixed>  $block
     */
    private function addEntry(array &$pages, array &$current, int &$y, array $block, string $reportTitle): void
    {
        $title = (string) ($block['title'] ?? 'Plan response');
        $kicker = (string) ($block['kicker'] ?? '');
        $body = (string) ($block['body'] ?? '');
        $note = (string) ($block['note'] ?? '');
        $points = array_values(array_filter(
            array_map('strval', (array) ($block['key_points'] ?? [])),
            fn (string $point): bool => $this->hasReadableText($point),
        ));

        $titleLines = $this->wrapForWidth($title, 12, self::MARGIN + 15);
        $headerHeight = max(50, 24 + ($kicker !== '' ? 12 : 0) + (count($titleLines) * 14));

        $this->ensureSpace(
            $pages,
            $current,
            $y,
            min(620, max(140, $this->estimateEntrySpace($title, $points, $body, $note))),
            $reportTitle,
        );
        $top = $y + 3;
        $this->addRect($current, self::MARGIN, $top - $headerHeight, 495, $headerHeight, [248, 245, 238]);
        $this->addRect($current, self::MARGIN, $top - $headerHeight, 5, $headerHeight, self::ACCENT);

        if ($kicker !== '') {
            $this->addText($current, $this->truncate($kicker, 66), self::MARGIN + 15, $top - 13, 7, 'F2', self::MUTED);
        }

        $titleY = (int) floor($top - ($kicker !== '' ? 27 : 17));
        foreach ($titleLines as $line) {
            $this->addText($current, $line, self::MARGIN + 15, $titleY, 12, 'F2', self::NAVY);
            $titleY -= 14;
        }
        $y = min($titleY - 8, $top - $headerHeight - 12);

        if ($points !== []) {
            $this->addText($current, 'Key points', self::MARGIN + 15, $y, 8, 'F2', self::GOLD);
            $y -= 14;
            $this->addBullets($pages, $current, $y, $points, $reportTitle, self::MARGIN + 15, 72);
        }

        if (trim($body) !== '') {
            $this->addText($current, 'Detail', self::MARGIN + 15, $y, 8, 'F2', self::MUTED);
            $y -= 13;
            $this->addParagraphText($pages, $current, $y, $body, $reportTitle, self::MARGIN + 15, 9, 12, [25, 31, 42]);
        }

        if ($note !== '') {
            $this->addWrapped($pages, $current, $y, $note, 8, 10, 'F1', self::MARGIN + 15, self::MUTED, $reportTitle);
            $this->addLine($pages, $current, $y, '', 10, 4, reportTitle: $reportTitle);
        }

        $this->addLineShape($current, self::MARGIN, $y + 4, 545, $y + 4, [238, 231, 219], 0.5);
        $this->addLine($pages, $current, $y, '', 10, 10, reportTitle: $reportTitle);
    }

    /**
     * @param  array<int, string>  $points
     */
    private function estimateEntrySpace(string $title, array $points, string $body, string $note): int
    {
        $titleLines = $this->wrapForWidth($title, 12, self::MARGIN + 15);
        $height = max(64, 42 + (count($titleLines) * 14));

        if ($points !== []) {
            $height += 14;
            foreach ($points as $point) {
                $height += (count($this->wrapForCharacters($point, 72)) * 12) + 2;
            }
        }

        if (trim($body) !== '') {
            $height += 13;
            foreach ($this->paragraphs($body) as $paragraph) {
                $height += (count($this->wrapForWidth($paragraph, 9, self::MARGIN + 15)) * 12) + 5;
            }
        }

        if ($note !== '') {
            $height += count($this->wrapForWidth($note, 8, self::MARGIN + 15)) * 10;
        }

        return $height + 18;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addParagraph(array &$pages, array &$current, int &$y, string $text, string $reportTitle): void
    {
        $this->addParagraphText($pages, $current, $y, $text, $reportTitle, self::MARGIN, 10, 13, [25, 31, 42]);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     */
    private function addParagraphText(array &$pages, array &$current, int &$y, string $text, string $reportTitle, int $x, int $size, int $leading, array $color): void
    {
        foreach ($this->paragraphs($text) as $paragraph) {
            $this->addWrapped($pages, $current, $y, $paragraph, $size, $leading, 'F1', $x, $color, $reportTitle);
            $this->addLine($pages, $current, $y, '', $size, 5, reportTitle: $reportTitle);
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, mixed>  $items
     */
    private function addBullets(array &$pages, array &$current, int &$y, array $items, string $reportTitle, int $x = self::MARGIN, int $characters = 90): void
    {
        foreach ($items as $item) {
            $this->ensureSpace($pages, $current, $y, 18, $reportTitle);
            $this->addText($current, '-', $x + 2, $y, 10, 'F2', self::GOLD);

            foreach ($this->wrapForCharacters((string) $item, $characters) as $line) {
                $this->addLine($pages, $current, $y, $line, 9, 12, 'F1', $x + 14, [25, 31, 42], $reportTitle);
            }

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
        $this->addTableHeader($current, $y, $headers, $xPositions, $tableWidth);

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
            $pageCount = count($pages);
            $this->ensureSpace($pages, $current, $y, $rowHeight + 6, $reportTitle);

            if (count($pages) > $pageCount) {
                $this->addTableHeader($current, $y, $headers, $xPositions, $tableWidth);
            }

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
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<int, string>  $headers
     * @param  array<int, float>  $xPositions
     */
    private function addTableHeader(array &$current, int &$y, array $headers, array $xPositions, float $tableWidth): void
    {
        $this->addRect($current, self::MARGIN, $y - 14, $tableWidth, 21, self::PAPER);

        foreach ($headers as $index => $header) {
            $this->addText($current, $header, $xPositions[$index] + 4, $y - 6, 7, 'F2', self::NAVY);
        }

        $y -= 24;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<string, mixed>  $block
     */
    private function addLineChart(array &$pages, array &$current, int &$y, array $block, string $reportTitle): void
    {
        $series = $this->chartSeries((array) ($block['series'] ?? []));

        if ($series === []) {
            return;
        }

        $pointCount = collect($series)
            ->map(fn (array $entry): int => count((array) $entry['values']))
            ->max() ?? 0;

        if ($pointCount < 2) {
            return;
        }

        $this->ensureSpace($pages, $current, $y, 205, $reportTitle);
        $title = (string) ($block['title'] ?? 'Trend');
        $note = (string) ($block['note'] ?? '');
        $labels = array_values(array_map('strval', (array) ($block['x_labels'] ?? [])));
        $values = collect($series)
            ->flatMap(fn (array $entry): array => (array) $entry['values'])
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();
        [$min, $max] = $this->chartRange($values);

        $this->addText($current, $title, self::MARGIN, $y, 11, 'F2', self::NAVY);
        $legendX = 360;
        foreach ($series as $entry) {
            $this->addRect($current, $legendX, $y - 5, 8, 8, (array) $entry['color']);
            $this->addText($current, (string) $entry['label'], $legendX + 12, $y - 3, 7, 'F1', self::MUTED);
            $legendX += 78;
        }
        $y -= 14;

        if ($note !== '') {
            $this->addWrapped($pages, $current, $y, $note, 8, 10, 'F1', self::MARGIN, self::MUTED, $reportTitle);
            $y -= 2;
        }

        $plotX = self::MARGIN + 34;
        $plotWidth = 430;
        $plotHeight = 108;
        $plotTop = $y - 8;
        $plotBottom = $plotTop - $plotHeight;
        $range = max(1.0, $max - $min);
        $xFor = fn (int $index): float => $pointCount === 1
            ? $plotX + ($plotWidth / 2)
            : $plotX + (($index / max(1, $pointCount - 1)) * $plotWidth);
        $yFor = fn (float $value): float => $plotBottom + ((($value - $min) / $range) * $plotHeight);

        $this->addRect($current, $plotX, $plotBottom, $plotWidth, $plotHeight, [252, 250, 244]);
        $this->addLineShape($current, $plotX, $plotBottom, $plotX, $plotTop, [198, 204, 196], 0.7);
        $this->addLineShape($current, $plotX, $plotBottom, $plotX + $plotWidth, $plotBottom, [198, 204, 196], 0.7);

        foreach ($this->chartTicks($min, $max) as $tick) {
            $tickY = $yFor($tick);
            $this->addLineShape($current, $plotX, $tickY, $plotX + $plotWidth, $tickY, [228, 232, 226], 0.4);
            $this->addText($current, $this->shortNumber($tick), self::MARGIN, $tickY - 2, 6, 'F1', self::MUTED);
        }

        if ($min < 0 && $max > 0) {
            $zeroY = $yFor(0);
            $this->addLineShape($current, $plotX, $zeroY, $plotX + $plotWidth, $zeroY, self::GOLD, 0.8);
        }

        foreach ($series as $entry) {
            $points = array_values((array) $entry['values']);
            $previous = null;

            for ($index = 0; $index < $pointCount; $index++) {
                if (! array_key_exists($index, $points) || ! is_numeric($points[$index])) {
                    continue;
                }

                $point = [$xFor($index), $yFor((float) $points[$index])];
                if ($previous !== null) {
                    $this->addLineShape($current, $previous[0], $previous[1], $point[0], $point[1], (array) $entry['color'], 1.4);
                }

                $previous = $point;
            }
        }

        foreach ($this->chartLabelIndexes($pointCount) as $index) {
            $label = $labels[$index] ?? 'M'.($index + 1);
            $x = $xFor($index);

            $this->addLineShape($current, $x, $plotBottom, $x, $plotBottom - 4, [198, 204, 196], 0.5);
            $this->addText($current, $label, $x - 6, $plotBottom - 14, 6, 'F1', self::MUTED);
        }

        $y = (int) floor($plotBottom - 28);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     * @param  array<int, array<string, mixed>>  $current
     * @param  array<string, mixed>  $block
     */
    private function addBarChart(array &$pages, array &$current, int &$y, array $block, string $reportTitle): void
    {
        $series = $this->chartSeries((array) ($block['series'] ?? []));
        $labels = array_values(array_map('strval', (array) ($block['x_labels'] ?? [])));
        $categoryCount = count($labels);

        if ($series === [] || $categoryCount === 0) {
            return;
        }

        $this->ensureSpace($pages, $current, $y, 205, $reportTitle);
        $title = (string) ($block['title'] ?? 'Chart');
        $note = (string) ($block['note'] ?? '');
        $values = collect($series)
            ->flatMap(fn (array $entry): array => array_slice((array) $entry['values'], 0, $categoryCount))
            ->map(fn (mixed $value): float => (float) $value)
            ->values()
            ->all();
        [$min, $max] = $this->chartRange($values);

        $this->addText($current, $title, self::MARGIN, $y, 11, 'F2', self::NAVY);
        $legendX = 320;
        foreach ($series as $entry) {
            $this->addRect($current, $legendX, $y - 5, 8, 8, (array) $entry['color']);
            $this->addText($current, (string) $entry['label'], $legendX + 12, $y - 3, 7, 'F1', self::MUTED);
            $legendX += 54;
        }
        $y -= 14;

        if ($note !== '') {
            $this->addWrapped($pages, $current, $y, $note, 8, 10, 'F1', self::MARGIN, self::MUTED, $reportTitle);
            $y -= 2;
        }

        $plotX = self::MARGIN + 34;
        $plotWidth = 430;
        $plotHeight = 108;
        $plotTop = $y - 8;
        $plotBottom = $plotTop - $plotHeight;
        $range = max(1.0, $max - $min);
        $yFor = fn (float $value): float => $plotBottom + ((($value - $min) / $range) * $plotHeight);
        $zeroY = $yFor(0);
        $seriesCount = max(1, count($series));
        $groupWidth = $plotWidth / max(1, $categoryCount);
        $barWidth = min(11.0, max(4.0, ($groupWidth - 12) / $seriesCount));

        $this->addRect($current, $plotX, $plotBottom, $plotWidth, $plotHeight, [252, 250, 244]);
        $this->addLineShape($current, $plotX, $zeroY, $plotX + $plotWidth, $zeroY, [198, 204, 196], 0.8);

        foreach ($this->chartTicks($min, $max) as $tick) {
            $tickY = $yFor($tick);
            $this->addLineShape($current, $plotX, $tickY, $plotX + $plotWidth, $tickY, [228, 232, 226], 0.4);
            $this->addText($current, $this->shortNumber($tick), self::MARGIN, $tickY - 2, 6, 'F1', self::MUTED);
        }

        foreach ($labels as $categoryIndex => $label) {
            $groupX = $plotX + ($categoryIndex * $groupWidth) + (($groupWidth - ($barWidth * $seriesCount)) / 2);

            foreach ($series as $seriesIndex => $entry) {
                $value = (float) (((array) $entry['values'])[$categoryIndex] ?? 0);
                $valueY = $yFor($value);
                $barX = $groupX + ($seriesIndex * $barWidth);
                $barY = min($zeroY, $valueY);
                $height = max(1.0, abs($valueY - $zeroY));

                $this->addRect($current, $barX, $barY, $barWidth - 1, $height, (array) $entry['color']);
            }

            $labelX = $plotX + ($categoryIndex * $groupWidth) + ($groupWidth / 2) - 6;
            $this->addText($current, $label, $labelX, $plotBottom - 14, 6, 'F1', self::MUTED);
        }

        $y = (int) floor($plotBottom - 28);
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
     * @param  array<int, mixed>  $series
     * @return array<int, array{label:string,values:array<int, float>,color:array<int, int>}>
     */
    private function chartSeries(array $series): array
    {
        $palette = [
            self::ACCENT,
            self::GOLD,
            self::NAVY,
            [95, 151, 135],
        ];

        return collect($series)
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry, int $index) use ($palette): array {
                $color = array_values((array) ($entry['color'] ?? $palette[$index % count($palette)]));

                return [
                    'label' => (string) ($entry['label'] ?? 'Series '.($index + 1)),
                    'values' => collect((array) ($entry['values'] ?? []))
                        ->map(fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0)
                        ->values()
                        ->all(),
                    'color' => [
                        (int) ($color[0] ?? $palette[$index % count($palette)][0]),
                        (int) ($color[1] ?? $palette[$index % count($palette)][1]),
                        (int) ($color[2] ?? $palette[$index % count($palette)][2]),
                    ],
                ];
            })
            ->filter(fn (array $entry): bool => $entry['values'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, float>  $values
     * @return array{0:float,1:float}
     */
    private function chartRange(array $values): array
    {
        $min = min(array_merge([0.0], $values));
        $max = max(array_merge([0.0], $values));

        if ($min === $max) {
            $padding = max(1.0, abs($max) * 0.2);

            return [$min - $padding, $max + $padding];
        }

        $padding = max(1.0, ($max - $min) * 0.08);

        return [$min - $padding, $max + $padding];
    }

    /**
     * @return array<int, float>
     */
    private function chartTicks(float $min, float $max): array
    {
        $range = max(1.0, $max - $min);
        $step = $range / 4;

        return [
            $min,
            $min + $step,
            $min + ($step * 2),
            $min + ($step * 3),
            $max,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function chartLabelIndexes(int $count): array
    {
        if ($count <= 6) {
            return range(0, max(0, $count - 1));
        }

        $indexes = [0, $count - 1];
        $step = max(1, (int) floor(($count - 1) / 4));

        for ($index = $step; $index < $count - 1; $index += $step) {
            $indexes[] = $index;
        }

        sort($indexes);

        return array_values(array_unique($indexes));
    }

    private function shortNumber(float $value): string
    {
        $prefix = $value < 0 ? '-' : '';
        $absolute = abs($value);

        if ($absolute >= 1_000_000) {
            return $prefix.'$'.rtrim(rtrim(number_format($absolute / 1_000_000, 1), '0'), '.').'m';
        }

        if ($absolute >= 1_000) {
            return $prefix.'$'.rtrim(rtrim(number_format($absolute / 1_000, 1), '0'), '.').'k';
        }

        return $prefix.'$'.number_format($absolute, 0);
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

    private function hasReadableText(string $text): bool
    {
        $text = $this->normalise($text);

        return preg_match('/[A-Za-z0-9]/', $text) === 1;
    }

    private function truncate(string $text, int $characters): string
    {
        $text = trim($this->normalise($text));

        if (strlen($text) <= $characters) {
            return $text;
        }

        return rtrim(substr($text, 0, max(0, $characters - 3))).'...';
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
