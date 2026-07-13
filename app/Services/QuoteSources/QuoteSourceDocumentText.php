<?php

declare(strict_types=1);

namespace App\Services\QuoteSources;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

final class QuoteSourceDocumentText
{
    /**
     * @return array<int, array{locator:string,text:string}>
     */
    public function chunks(Document $document): array
    {
        $bytes = Storage::disk('secure_local')->get($document->stored_path);
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'docx' => $this->docxChunks($bytes),
            'xlsx' => $this->xlsxChunks($bytes),
            'pdf' => $this->pdfChunks($bytes),
            'csv', 'txt' => $this->lineChunks($bytes),
            default => [],
        };
    }

    /**
     * @return array<int, array{locator:string,text:string}>
     */
    private function lineChunks(string $bytes): array
    {
        return collect(preg_split('/\R/u', $bytes) ?: [])
            ->map(fn (string $line, int $index): array => [
                'locator' => 'line:'.($index + 1),
                'text' => $this->normalise($line),
            ])
            ->filter(fn (array $chunk): bool => $chunk['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locator:string,text:string}>
     */
    private function docxChunks(string $bytes): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $xml = $this->zipEntry($bytes, 'word/document.xml');
        if ($xml === null) {
            return [];
        }

        preg_match_all('/<w:p\\b[^>]*>(.*?)<\\/w:p>/si', $xml, $paragraphs);

        return collect($paragraphs[1] ?? [])
            ->map(fn (string $paragraph, int $index): array => [
                'locator' => 'paragraph:'.($index + 1),
                'text' => $this->normalise(html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            ])
            ->filter(fn (array $chunk): bool => $chunk['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locator:string,text:string}>
     */
    private function xlsxChunks(string $bytes): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $path = tempnam(sys_get_temp_dir(), 'fsa-quote-source-');
        if (! is_string($path) || file_put_contents($path, $bytes) === false) {
            return [];
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                return [];
            }

            $sharedStrings = $this->sharedStrings($zip);
            $chunks = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || ! preg_match('/^xl\\/worksheets\\/sheet(\\d+)\\.xml$/', $name, $matches)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (! is_string($xml)) {
                    continue;
                }

                preg_match_all('/<c\\b([^>]*)>(.*?)<\\/c>/si', $xml, $cells, PREG_SET_ORDER);
                foreach ($cells as $cell) {
                    $reference = '';
                    if (preg_match('/\\br="([^"]+)"/', (string) $cell[1], $referenceMatch)) {
                        $reference = $referenceMatch[1];
                    }
                    preg_match('/<v>(.*?)<\\/v>/si', (string) $cell[2], $valueMatch);
                    $value = html_entity_decode(strip_tags((string) ($valueMatch[1] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if (str_contains((string) $cell[1], 't="s"') && is_numeric($value)) {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }

                    $value = $this->normalise($value);
                    if ($value !== '') {
                        $chunks[] = [
                            'locator' => 'sheet:'.$matches[1].':'.($reference !== '' ? $reference : 'cell'),
                            'text' => $value,
                        ];
                    }
                }
            }

            $zip->close();

            return $chunks;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<int, array{locator:string,text:string}>
     */
    private function pdfChunks(string $bytes): array
    {
        preg_match_all('/\\(([^()]|\\\\[()\\\\]){3,}\\)/', $bytes, $matches);
        $text = collect($matches[0] ?? [])
            ->map(fn (string $segment): string => trim(stripslashes(substr($segment, 1, -1))))
            ->filter()
            ->implode(' ');
        $text = $this->normalise($text);

        if ($text === '') {
            return [];
        }

        return collect(str_split($text, 1200))
            ->map(fn (string $chunk, int $index): array => [
                'locator' => 'page:1:chunk:'.($index + 1),
                'text' => $chunk,
            ])
            ->all();
    }

    private function zipEntry(string $bytes, string $entry): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-quote-source-');
        if (! is_string($path) || file_put_contents($path, $bytes) === false) {
            return null;
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                return null;
            }

            $content = $zip->getFromName($entry);
            $zip->close();

            return is_string($content) ? $content : null;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($xml)) {
            return [];
        }

        preg_match_all('/<si\\b[^>]*>(.*?)<\\/si>/si', $xml, $strings);

        return collect($strings[1] ?? [])
            ->map(fn (string $value): string => $this->normalise(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')))
            ->all();
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\\s+/u', ' ', $value));
    }
}
