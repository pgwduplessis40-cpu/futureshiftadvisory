<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\SimpleTextPdf;
use App\Services\Reports\BrandedReportLayout;
use Illuminate\Support\Str;
use Throwable;

/**
 * @phpstan-import-type ReleaseStatus from FounderBriefRelease as FunderReadyBriefStatus
 * @phpstan-import-type BriefPage from FounderBriefPages
 */
final class FunderReadyBriefBuilder
{
    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly SimpleTextPdf $fallbackPdf,
        private readonly BrandedReportLayout $layout,
        private readonly BusinessPlanIdentity $identity,
        private readonly EntrepreneurDocumentTemplate $templates,
        private readonly FounderBriefRelease $release,
        private readonly FounderBriefPages $pages,
    ) {}

    /** @return FunderReadyBriefStatus */
    public function status(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        return $this->release->evaluate($plan, $profile);
    }

    public function filename(EntrepreneurProfile $profile, bool $preview = false): string
    {
        return Str::slug($profile->name ?: 'entrepreneur').'-founder-brief'.($preview ? '-advisor-draft' : '').'.pdf';
    }

    public function pdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        app(ReleaseGate\ReleaseGate::class)->assertExternalRelease($plan, 'brief', auth()->user());
    }

    public function previewPdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $status = $this->status($plan, $profile);
        abort_unless($status['can_generate'], 409, $status['generation_reason'] ?? 'Complete and assess the current business plan and budget before generating the founder brief.');

        return app(ReleaseGate\DraftDelivery::class)->render($plan, 'brief', function () use ($profile, $plan): ReleaseGate\RenderedDraft {
            $current = $this->status($plan, $profile);
            abort_unless($current['can_generate'], 409, $current['generation_reason'] ?? 'The current plan and budget are not ready for a draft.');

            return $this->render($profile, $plan, $current, true);
        });
    }

    /** @param FunderReadyBriefStatus $status */
    private function render(EntrepreneurProfile $profile, BusinessPlan $plan, array $status, bool $preview): ReleaseGate\RenderedDraft
    {
        $pages = $this->pages->build($profile, $plan, $status, $preview);
        $name = $this->identity->businessName($profile, $plan, $plan->sections->pluck('body')->filter()->all()) ?? 'Business plan';
        $label = $preview ? 'ADVISOR DRAFT - not approved for external issue' : 'Founder-ready brief';
        $footer = 'Future Shift Advisory | '.$label;

        try {
            $html = $this->html($name, $pages, $status, $label, $footer);

            return new ReleaseGate\RenderedDraft($this->pdf->render($html), 'primary', $html);
        } catch (Throwable $exception) {
            report($exception);

            return new ReleaseGate\RenderedDraft($this->fallback($name, $pages, $footer), 'fallback');
        }
    }

    /**
     * @param  list<BriefPage>  $pages
     * @param  FunderReadyBriefStatus  $status
     */
    private function html(string $name, array $pages, array $status, string $label, string $footer): string
    {
        $content = '';
        foreach ($pages as $index => $page) {
            $content .= '<article class="brief-page'.($index === 0 ? ' brief-cover' : '').'"><p class="page-label">'.($index + 1).' / '.count($pages).'</p><h2>'.$this->escape($page['title']).'</h2>';
            foreach ($page['paragraphs'] as $paragraph) {
                $content .= '<p>'.$this->escape($paragraph).'</p>';
            }
            foreach ($page['tables'] as $table) {
                $content .= '<h3>'.$this->escape($table['title']).'</h3><table><thead><tr>';
                foreach ($table['headers'] as $cell) {
                    $content .= '<th>'.$this->escape($cell).'</th>';
                }
                $content .= '</tr></thead><tbody>';
                foreach ($table['rows'] as $row) {
                    $content .= '<tr>';
                    foreach ($row as $cell) {
                        $content .= '<td>'.$this->escape($cell).'</td>';
                    }
                    $content .= '</tr>';
                }
                $content .= '</tbody></table>';
            }
            $content .= '</article>';
        }
        $template = $this->templates->businessPlan();

        return $this->layout->document(
            title: 'Founder Brief - '.$name,
            templateKey: $template?->getKey() ?? EntrepreneurDocumentTemplate::BUSINESS_PLAN,
            documentTag: 'Founder brief',
            eyebrow: $label,
            heading: $name,
            subheading: 'Business case and financial forecast',
            meta: ['Prepared' => now()->format('j M Y'), 'Summary' => $status['executive_summary']['usable'] ? 'Current' : 'Not ready', 'Source version' => substr($status['approval_token'], 0, 12)],
            contentHtml: $content,
            footer: $footer,
            template: $template,
            extraCss: <<<'CSS'
.brief-page { break-before: page; }
.brief-cover { break-before: auto; }
.brief-page h2 { border-top: 4px solid #0d7a7a; color: #1c2f4a; padding-top: 12px; font-size: 22px; margin: 0 0 18px; }
.brief-page h3 { color: #1c2f4a; font-size: 14px; margin: 18px 0 8px; }
.brief-page p { font-size: 13px; line-height: 1.65; margin: 0 0 16px; overflow-wrap: anywhere; }
.brief-page .page-label { color: #667282; font-size: 10px; text-align: right; margin-bottom: 6px; }
.brief-page table { table-layout: fixed; font-size: 11px; line-height: 1.45; }
.brief-page td, .brief-page th { padding: 9px 7px; overflow-wrap: anywhere; vertical-align: top; }
.brief-page tr { break-inside: avoid; }
.brief-page th { background: #f4f0e7; color: #1c2f4a; }
.brief-page td:first-child, .brief-page th:first-child { width: 34%; }
.brief-cover table { font-size: 11px; }
.brief-cover td, .brief-cover th { padding: 4px 7px; text-align: left; }
.brief-cover td:first-child, .brief-cover th:first-child { width: 9%; }
.report-hero h1 { font-size: 27px; overflow-wrap: anywhere; }
CSS,
        );
    }

    /** @param list<BriefPage> $pages */
    private function fallback(string $name, array $pages, string $footer): string
    {
        $blocks = [];
        foreach ($pages as $index => $page) {
            if ($index > 0) {
                $blocks[] = ['type' => 'page_break'];
            }
            $blocks[] = ['type' => 'section', 'text' => ($index + 1).'. '.$page['title']];
            if ($index === 0) {
                $blocks[] = ['type' => 'paragraph', 'text' => $name.' | '.$footer];
            }
            foreach ($page['paragraphs'] as $paragraph) {
                $blocks[] = ['type' => 'paragraph', 'text' => $paragraph];
            }
            foreach ($page['tables'] as $table) {
                $blocks[] = ['type' => 'section', 'text' => $table['title']];
                $widths = array_fill(0, count($table['headers']), 1);
                $widths[0] = $index === 0 ? 0.12 : max(1, (count($widths) - 1) * 0.52);
                $blocks[] = ['type' => 'table', 'headers' => $table['headers'], 'rows' => $table['rows'], 'widths' => $widths];
            }
        }

        return $this->fallbackPdf->renderStructured('Founder Brief', $blocks, $footer);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
