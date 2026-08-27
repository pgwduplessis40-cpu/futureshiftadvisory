<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Support\Str;

/**
 * Resolves the one report template that may shape a report and exposes the
 * stable metadata contract persisted with that report. Composition and
 * rendering use this shared contract rather than duplicate template rules.
 *
 * @phpstan-type ReportTemplateMetadata array{
 *     id:string,
 *     category:string,
 *     title:string,
 *     version:int,
 *     source_reference:?string,
 *     structure_report_type:?string,
 *     render_strategy:string,
 *     updated_at:string,
 *     uploaded_file_document_id:?string,
 *     uploaded_file_sha256:?string,
 *     uploaded_file_scanner_result:?string,
 *     uploaded_file:?string
 * }
 */
final class ReportTemplateCatalog
{
    public function __construct(private readonly UploadedReportTemplateRenderer $uploadedTemplates) {}

    public function activeFor(ReportType $type): ?Template
    {
        return Template::query()
            ->usable()
            ->where('category', Template::CATEGORY_REPORT)
            ->get()
            ->filter(fn (Template $template): bool => $this->appliesTo($template, $type)
                && $this->hasRenderableSource($template))
            ->sort(fn (Template $left, Template $right): int => $this->selectionRank($right, $type) <=> $this->selectionRank($left, $type))
            ->first();
    }

    /** @return ReportTemplateMetadata|null */
    public function metadata(?Template $template): ?array
    {
        if (! $template instanceof Template) {
            return null;
        }

        $uploadedFile = data_get($template->structure, 'uploaded_file');
        $uploadedFile = is_array($uploadedFile) ? $uploadedFile : [];

        return [
            'id' => (string) $template->getKey(),
            'category' => (string) $template->category,
            'title' => (string) $template->title,
            'version' => (int) $template->version,
            'source_reference' => $this->nullableString($template->source_reference),
            'structure_report_type' => $this->nullableString(data_get($template->structure, 'report_type')),
            'render_strategy' => $this->renderStrategy($template),
            'updated_at' => $template->updated_at->toIso8601String(),
            'uploaded_file_document_id' => $this->nullableString($uploadedFile['document_id'] ?? null),
            'uploaded_file_sha256' => $this->nullableString($uploadedFile['sha256'] ?? null),
            'uploaded_file_scanner_result' => $uploadedFile === [] ? null : $this->uploadedFileScannerResult($template),
            'uploaded_file' => $this->nullableString($uploadedFile['original_name'] ?? null),
        ];
    }

    public function isTokenizedHtml(Template $template): bool
    {
        $body = Str::lower((string) $template->body);

        return Str::contains($body, [
            '{{ sections',
            '{{sections',
            '<html',
            '<body',
            '<style',
            'data-report-template',
        ]);
    }

    private function renderStrategy(Template $template): string
    {
        if ($this->uploadedTemplates->supports($template)) {
            return 'uploaded_docx_html_v5';
        }

        if ($this->isTokenizedHtml($template)) {
            return 'tokenized_html_v1';
        }

        return 'branded_html_v1';
    }

    private function appliesTo(Template $template, ReportType $type): bool
    {
        $reportType = data_get($template->structure, 'report_type');

        if (is_string($reportType) && trim($reportType) !== '') {
            return $reportType === $type->value;
        }

        return $this->titleMatchesType($template, $type)
            || $this->isGeneric($template);
    }

    /** @return array{0:int,1:int,2:int,3:int,4:int,5:string} */
    private function selectionRank(Template $template, ReportType $type): array
    {
        return [
            $this->sourceRank($template),
            $this->specificityRank($template, $type),
            $template->updated_at->getTimestamp(),
            $template->created_at->getTimestamp(),
            (int) $template->version,
            (string) $template->getKey(),
        ];
    }

    private function sourceRank(Template $template): int
    {
        if (data_get($template->structure, 'source_kind') === 'uploaded_file'
            || is_array(data_get($template->structure, 'uploaded_file'))) {
            return $this->uploadedTemplates->supports($template)
                ? 2
                : (trim((string) $template->body) !== '' ? 1 : 0);
        }

        return trim((string) $template->body) !== '' ? 1 : 0;
    }

    private function hasRenderableSource(Template $template): bool
    {
        return $this->uploadedTemplates->supports($template)
            || trim((string) $template->body) !== '';
    }

    private function specificityRank(Template $template, ReportType $type): int
    {
        $reportType = data_get($template->structure, 'report_type');

        if (is_string($reportType) && $reportType === $type->value) {
            return 2;
        }

        return $this->titleMatchesType($template, $type) ? 1 : 0;
    }

    private function titleMatchesType(Template $template, ReportType $type): bool
    {
        return Str::contains(Str::lower((string) $template->title), $this->keywordsFor($type));
    }

    private function isGeneric(Template $template): bool
    {
        $reportType = data_get($template->structure, 'report_type');

        return (! is_string($reportType) || trim($reportType) === '')
            && ! Str::contains(Str::lower((string) $template->title), ['client', 'advisor', 'stakeholder', 'trajectory']);
    }

    private function uploadedFileScannerResult(Template $template): string
    {
        $scannerResult = data_get($template->structure, 'uploaded_file.scanner_result');
        if (is_string($scannerResult) && $scannerResult !== '') {
            return $scannerResult;
        }

        $documentId = data_get($template->structure, 'uploaded_file.document_id');
        if (is_string($documentId) && $documentId !== '') {
            $document = Document::query()->find($documentId);

            if ($document instanceof Document) {
                return (string) $document->scanner_result;
            }
        }

        return Document::SCANNER_CLEAN;
    }

    /** @return list<string> */
    private function keywordsFor(ReportType $type): array
    {
        return match ($type) {
            ReportType::Client => ['client report', 'client'],
            ReportType::Advisor => ['advisor report', 'advisor'],
            ReportType::Stakeholder => ['stakeholder report', 'stakeholder'],
            ReportType::Trajectory => ['business health trajectory report', 'trajectory'],
            default => [Str::lower($type->label()), Str::of($type->value)->replace('_', ' ')->lower()->toString()],
        };
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
