<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\Template;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Reports\Contracts\ReportArtifactRenderer;
use App\Services\Storage\SecureFileWriter;
use App\Support\Reports\SourceReferenceLabeler;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared durable-artifact renderer for every report-type composer.
 *
 * Composition decides which typed sections belong in a report. This boundary
 * owns how those sections become stored PDF/PPTX artifacts and ensures a
 * rendering failure is recorded consistently for every report type.
 */
final class StoredReportArtifactRenderer implements ReportArtifactRenderer
{
    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly PptxGenerator $pptx,
        private readonly UploadedReportTemplateRenderer $uploadedTemplates,
        private readonly ReportTemplateCatalog $templateCatalog,
        private readonly BrandedReportLayout $layout,
        private readonly SecureFileWriter $files,
    ) {}

    public function render(Report $report, bool $withPptx = false): void
    {
        try {
            $report->loadMissing(['client', 'entrepreneurProfile', 'sections']);
            $this->renderAndStorePdf($report);

            if ($withPptx) {
                $this->renderAndStorePptx($report->refresh()->load(['client', 'entrepreneurProfile', 'sections']));
            }

            $this->markRendered($report);
        } catch (Throwable $exception) {
            $this->markRenderFailed($report, $exception);

            throw $exception;
        }
    }

    public function rerender(Report $report): void
    {
        $report->loadMissing(['client', 'entrepreneurProfile', 'sections']);
        $withPptx = $report->pptx_path !== null;

        $report->forceFill([
            'render_status' => Report::RENDER_STATUS_COMPOSING,
            'render_failed_at' => null,
            'render_error' => null,
        ])->save();

        $this->render($report, $withPptx);
    }

    public function usesCurrentTemplate(Report $report): bool
    {
        return ($report->metadata['template'] ?? null) === $this->templateCatalog->metadata(
            $this->templateForReport($report),
        );
    }

    private function renderAndStorePdf(Report $report): void
    {
        $pdf = $this->pdf->render($this->html($report));
        $path = sprintf(
            'reports/%s/%s/%s-%s.pdf',
            $this->reportSubjectKey($report),
            now()->format('Y/m'),
            Str::uuid(),
            $report->type->value,
        );

        $this->files->writeGenerated($path, $pdf);

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_byte_size' => strlen($pdf),
            'render_failed_at' => null,
            'render_error' => null,
        ])->save();
    }

    private function renderAndStorePptx(Report $report): void
    {
        $pptx = $this->pptx->render($report);
        $path = sprintf(
            'reports/%s/%s/%s-%s.pptx',
            $this->reportSubjectKey($report),
            now()->format('Y/m'),
            Str::uuid(),
            $report->type->value,
        );

        $this->files->writeGenerated($path, $pptx);

        $report->forceFill([
            'pptx_path' => $path,
            'pptx_byte_size' => strlen($pptx),
        ])->save();
    }

    private function markRendered(Report $report): void
    {
        $report->forceFill([
            'render_status' => Report::RENDER_STATUS_RENDERED,
            'render_failed_at' => null,
            'render_error' => null,
        ])->save();
    }

    private function markRenderFailed(Report $report, Throwable $exception): void
    {
        $report->forceFill([
            'render_status' => Report::RENDER_STATUS_FAILED,
            'render_failed_at' => now(),
            'render_error' => Str::limit($exception::class.': '.$exception->getMessage(), 4000, ''),
        ])->save();
    }

    private function html(Report $report): string
    {
        $report->loadMissing(['client.primaryContact', 'entrepreneurProfile', 'sections']);
        $sections = $report->sections
            ->sortBy('position')
            ->map(fn (ReportSection $section): string => $this->sectionHtml($section))
            ->implode('');
        $template = $this->templateForReport($report);
        $this->syncReportTemplateMetadata($report, $template);

        if ($template instanceof Template && $this->templateCatalog->isTokenizedHtml($template)) {
            return $this->htmlFromTemplate($report, $template, $sections);
        }

        if ($template instanceof Template) {
            $html = $this->uploadedTemplates->render(
                $report,
                $template,
                $sections,
                $this->reportTemplateTokens($report, $template, $sections),
                $this->reportCss($template),
            );

            if (is_string($html)) {
                return $html;
            }
        }

        return $this->brandedReportHtml($report, $template, $sections);
    }

    private function templateForReport(Report $report): ?Template
    {
        return $this->templateCatalog->activeFor($report->type);
    }

    private function syncReportTemplateMetadata(Report $report, ?Template $template): void
    {
        $metadata = $report->metadata ?? [];
        $templateMetadata = $this->templateCatalog->metadata($template);

        if (($metadata['template'] ?? null) === $templateMetadata) {
            return;
        }

        $metadata['template'] = $templateMetadata;
        $report->forceFill(['metadata' => $metadata])->save();
    }

    private function htmlFromTemplate(Report $report, Template $template, string $sections): string
    {
        $body = (string) $template->body;
        $hasSectionsToken = Str::contains($body, [
            '{{ sections }}',
            '{{sections}}',
            '{{{ sections }}}',
            '{{{sections}}}',
        ]);
        $rendered = strtr($body, $this->reportTemplateTokens($report, $template, $sections));

        if (! $hasSectionsToken) {
            $rendered .= "\n".$sections;
        }

        if (Str::contains(Str::lower($rendered), '<html')) {
            return $rendered;
        }

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>%s</style>
</head>
<body data-report-template="%s">
%s
</body>
</html>
HTML,
            $this->escape($report->title),
            $this->reportCss($template),
            $this->escape((string) $template->getKey()),
            $rendered,
        );
    }

    /**
     * @return array<string, string>
     */
    private function reportTemplateTokens(Report $report, Template $template, string $sections): array
    {
        $clientName = $this->reportSubjectName($report);
        $generatedAt = $report->generated_at?->format('j M Y') ?? now()->format('j M Y');
        $primaryContact = $this->primaryContact($report);
        $primaryTitle = $primaryContact instanceof User ? 'Primary contact' : 'Client';
        $engagementPeriod = is_string(data_get($report->metadata, 'engagement_period'))
            ? (string) data_get($report->metadata, 'engagement_period')
            : 'As at '.$generatedAt;

        return [
            '{{ report_title }}' => $this->escape($report->title),
            '{{report_title}}' => $this->escape($report->title),
            '{{ report_type }}' => $this->escape($report->type->label()),
            '{{report_type}}' => $this->escape($report->type->label()),
            '{{ client_name }}' => $this->escape($clientName),
            '{{client_name}}' => $this->escape($clientName),
            '{{ generated_at }}' => $this->escape($generatedAt),
            '{{generated_at}}' => $this->escape($generatedAt),
            '{{ template_title }}' => $this->escape($template->title),
            '{{template_title}}' => $this->escape($template->title),
            '{{ template_version }}' => (string) $template->version,
            '{{template_version}}' => (string) $template->version,
            '{{ sections }}' => $sections,
            '{{sections}}' => $sections,
            '{{{ sections }}}' => $sections,
            '{{{sections}}}' => $sections,
            '[Business Name]' => $this->escape($clientName),
            '[Report Type]' => $this->escape($report->type->label()),
            '[Date]' => $this->escape($generatedAt),
            '[Engagement Period]' => $this->escape($engagementPeriod),
            '[Client Primary Contact]' => $this->escape($primaryContact instanceof User ? $primaryContact->name : 'Client contact'),
            '[Title]' => $this->escape($primaryTitle),
        ];
    }

    private function brandedReportHtml(Report $report, ?Template $template, string $sections): string
    {
        $clientName = $this->reportSubjectName($report);
        $templateLabel = $template instanceof Template
            ? sprintf('%s v%d', $template->title, $template->version)
            : 'System report layout';

        return $this->layout->document(
            title: $report->title,
            templateKey: $template instanceof Template ? (string) $template->getKey() : 'system',
            documentTag: $report->type->label(),
            eyebrow: $report->type->label(),
            heading: $report->title,
            subheading: $clientName,
            meta: [
                'Report type' => $report->type->label(),
                'Generated' => $report->generated_at?->format('j M Y') ?? now()->format('j M Y'),
                'Template' => $templateLabel,
            ],
            contentHtml: $sections,
            footer: 'Generated using '.$templateLabel,
            template: $template,
        );
    }

    private function reportCss(?Template $template): string
    {
        return $this->layout->css($template, fixedFooter: false);
    }

    private function reportSubjectKey(Report $report): string
    {
        $clientId = $report->getAttribute('client_id');

        if (is_string($clientId) && $clientId !== '') {
            return $clientId;
        }

        $profileId = $report->getAttribute('entrepreneur_profile_id');

        return 'entrepreneur-'.(is_string($profileId) ? $profileId : 'unassigned');
    }

    private function reportSubjectName(Report $report): string
    {
        $client = $report->getRelation('client');

        if ($client instanceof Client) {
            return $client->legal_name;
        }

        $profile = $report->getRelation('entrepreneurProfile');

        return $profile instanceof EntrepreneurProfile ? $profile->name : 'Client';
    }

    private function primaryContact(Report $report): ?User
    {
        $client = $report->getRelation('client');

        if (! $client instanceof Client) {
            return null;
        }

        $contact = $client->getRelation('primaryContact');

        return $contact instanceof User ? $contact : null;
    }

    private function sectionHtml(ReportSection $section): string
    {
        $sources = $this->sectionSourceLabels($section);
        $chart = is_string($section->metadata['chart_html'] ?? null)
            ? '<div class="chart">'.$section->metadata['chart_html'].'</div>'
            : '';
        $evidenceLines = collect([
            $section->document_support_note,
            $section->data_quality_note,
            $sources === '' ? '' : 'Sources: '.$sources,
        ])
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->map(fn (string $line): string => '<p>'.$this->escape($line).'</p>')
            ->implode('');
        $evidence = $evidenceLines === '' ? '' : '<div class="evidence">'.$evidenceLines.'</div>';

        return sprintf(
            <<<'HTML'
<article class="report-section" data-section-key="%s">
<h2>%s</h2>
<div class="section-body">%s</div>
%s
%s
</article>
HTML,
            $this->escape($section->key),
            $this->escape($section->title),
            nl2br($this->escape($section->body)),
            $chart,
            $evidence,
        );
    }

    private function sectionSourceLabels(ReportSection $section): string
    {
        return collect($section->attributions ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): string => SourceReferenceLabeler::label(
                (string) ($item['source_reference'] ?? ''),
                isset($item['claim']) ? (string) $item['claim'] : null,
            ))
            ->filter()
            ->unique()
            ->take(5)
            ->implode(', ');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
