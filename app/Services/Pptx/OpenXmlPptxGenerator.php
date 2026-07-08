<?php

declare(strict_types=1);

namespace App\Services\Pptx;

use App\Models\Report;
use App\Models\ReportSection;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Support\Reports\SourceReferenceLabeler;
use RuntimeException;
use ZipArchive;

final class OpenXmlPptxGenerator implements PptxGenerator
{
    public function render(Report $report): string
    {
        $report->loadMissing(['client', 'sections']);
        $slides = $this->slides($report);
        $path = tempnam(sys_get_temp_dir(), 'fsa-pptx-');

        if ($path === false) {
            throw new RuntimeException('Could not allocate temporary PPTX file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('Could not open temporary PPTX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($slides)));
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('ppt/presentation.xml', $this->presentation(count($slides)));
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $this->presentationRels(count($slides)));

        foreach ($slides as $index => $slide) {
            $zip->addFromString('ppt/slides/slide'.($index + 1).'.xml', $this->slide($slide['title'], $slide['body']));
        }

        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);

        if ($bytes === false) {
            throw new RuntimeException('Could not read generated PPTX archive.');
        }

        return $bytes;
    }

    /**
     * @return array<int, array{title:string, body:string}>
     */
    private function slides(Report $report): array
    {
        $generatedAt = $report->generated_at?->toFormattedDateString() ?? '';

        $slides = [[
            'title' => $report->title,
            'body' => ($report->client?->legal_name ?? 'Client')."\n".$generatedAt,
        ]];

        foreach ($report->sections->sortBy('position')->take(10) as $section) {
            $slides[] = [
                'title' => $section->title,
                'body' => $this->body($section),
            ];
        }

        return $slides;
    }

    private function body(ReportSection $section): string
    {
        $sources = collect($section->attributions ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): string => SourceReferenceLabeler::label(
                (string) ($item['source_reference'] ?? ''),
                isset($item['claim']) ? (string) $item['claim'] : null,
            ))
            ->filter()
            ->unique()
            ->implode(', ');

        $lines = collect([
            $section->body,
            $section->document_support_note,
            $section->data_quality_note,
            $sources === '' ? '' : 'Sources: '.$sources,
        ])->filter(fn (mixed $line): bool => is_string($line) && trim($line) !== '');

        return trim($lines->implode("\n\n"));
    }

    private function contentTypes(int $slides): string
    {
        $overrides = '';

        for ($i = 1; $i <= $slides; $i++) {
            $overrides .= '<Override PartName="/ppt/slides/slide'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
            .'</Relationships>';
    }

    private function presentation(int $slides): string
    {
        $ids = '';

        for ($i = 1; $i <= $slides; $i++) {
            $ids .= '<p:sldId id="'.(255 + $i).'" r:id="rId'.$i.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<p:sldIdLst>'.$ids.'</p:sldIdLst>'
            .'<p:sldSz cx="12192000" cy="6858000" type="wide"/>'
            .'<p:notesSz cx="6858000" cy="9144000"/>'
            .'</p:presentation>';
    }

    private function presentationRels(int $slides): string
    {
        $rels = '';

        for ($i = 1; $i <= $slides; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$i.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function slide(string $title, string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<p:cSld><p:spTree>'
            .'<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
            .$this->shape(2, 'Title', 610000, 420000, 10900000, 900000, $title, 3200)
            .$this->shape(3, 'Body', 760000, 1500000, 10600000, 4300000, $body, 1500)
            .'</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }

    private function shape(int $id, string $name, int $x, int $y, int $cx, int $cy, string $text, int $fontSize): string
    {
        $paragraphs = collect(explode("\n", $text))
            ->map(fn (string $line): string => '<a:p><a:r><a:rPr lang="en-NZ" sz="'.$fontSize.'"/><a:t>'.$this->xml($line).'</a:t></a:r></a:p>')
            ->implode('');

        return '<p:sp>'
            .'<p:nvSpPr><p:cNvPr id="'.$id.'" name="'.$this->xml($name).'"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            .'<p:spPr><a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
            .'<p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>'.$paragraphs.'</p:txBody>'
            .'</p:sp>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(strip_tags($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
