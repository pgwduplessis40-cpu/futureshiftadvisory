<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Template;

final class BrandedReportLayout
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function document(
        string $title,
        string $templateKey,
        string $documentTag,
        string $eyebrow,
        string $heading,
        string $subheading,
        array $meta,
        string $contentHtml,
        string $footer,
        ?Template $template = null,
        string $snapshotTitle = 'Report snapshot',
        int $metaColumns = 3,
        string $extraCss = '',
    ): string {
        $metaHtml = $this->metaHtml($meta);
        $css = $this->css($template, $metaColumns);

        if ($extraCss !== '') {
            $css .= "\n".$extraCss;
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
<header class="letterhead">
<div class="brand-lockup" aria-label="Future Shift Advisory">
<div class="brand-mark"><span></span><span></span><span></span></div>
<div><p class="brand-name">Future Shift</p><p class="brand-subtitle">ADVISORY</p></div>
</div>
<div class="document-tag">%s</div>
</header>
<section class="report-hero">
<p class="eyebrow">%s</p>
<h1>%s</h1>
<p>%s</p>
</section>
<section class="report-snapshot">
<h2>%s</h2>
<dl class="report-meta">
%s
</dl>
</section>
<main class="report-content">
%s
</main>
<footer class="report-footer">%s</footer>
</body>
</html>
HTML,
            $this->escape($title),
            $css,
            $this->escape($templateKey),
            $this->escape($documentTag),
            $this->escape($eyebrow),
            $this->escape($heading),
            $this->escape($subheading),
            $this->escape($snapshotTitle),
            $metaHtml,
            $contentHtml,
            $this->escape($footer),
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function metaHtml(array $meta): string
    {
        return collect($meta)
            ->map(fn (mixed $value, string $label): string => sprintf(
                '<div><dt>%s</dt><dd>%s</dd></div>',
                $this->escape($label),
                $this->escape($value),
            ))
            ->implode("\n");
    }

    public function section(string $title, string $bodyHtml, string $class = '', ?string $key = null): string
    {
        $classes = trim('report-section '.$class);
        $keyAttribute = $key === null ? '' : sprintf(' data-section-key="%s"', $this->escape($key));

        return sprintf(
            '<article class="%s"%s><h2>%s</h2>%s</article>',
            $this->escape($classes),
            $keyAttribute,
            $this->escape($title),
            $bodyHtml,
        );
    }

    public function css(?Template $template = null, int $metaColumns = 3): string
    {
        $accent = $this->templateLayoutColor($template, 'accent_color', '#0d7a7a');
        $accentDark = $this->templateLayoutColor($template, 'accent_dark', '#1c2f4a');
        $ink = $this->templateLayoutColor($template, 'ink_color', '#13233a');
        $muted = $this->templateLayoutColor($template, 'muted_color', '#667282');
        $paper = $this->templateLayoutColor($template, 'paper_color', '#ffffff');
        $metaColumns = max(1, min(5, $metaColumns));

        return <<<CSS
@page { margin: 15mm 15mm 18mm; }
* { box-sizing: border-box; }
body { background: {$paper}; color: {$ink}; font-family: Arial, sans-serif; font-size: 11.5px; line-height: 1.55; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.letterhead { align-items: center; border-top: 7px solid #1c2f4a; border-bottom: 1px solid #d8d1c2; display: flex; justify-content: space-between; margin-bottom: 18px; padding: 13px 0 12px; }
.brand-lockup { align-items: center; display: inline-flex; gap: 13px; }
.brand-mark { align-items: end; display: inline-flex; gap: 3px; height: 36px; width: 38px; }
.brand-mark span { background: {$accent}; display: block; width: 8px; }
.brand-mark span:nth-child(1) { height: 14px; opacity: .55; }
.brand-mark span:nth-child(2) { height: 24px; opacity: .78; }
.brand-mark span:nth-child(3) { height: 34px; }
.brand-name { color: {$accentDark}; font-size: 15px; font-weight: 700; line-height: 1; margin: 0; }
.brand-subtitle { color: #5a7a70; font-size: 8px; font-weight: 700; letter-spacing: 0; margin: 4px 0 0; }
.document-tag { background: #f4efe3; border: 1px solid #d8d1c2; border-radius: 999px; color: {$accentDark}; font-size: 10px; font-weight: 700; padding: 5px 11px; }
.report-hero { background: #f8f5ee; border: 1px solid #ded6c7; border-left: 5px solid #b8860b; margin-bottom: 14px; padding: 16px 18px; }
.eyebrow { color: {$accent}; font-size: 9px; font-weight: 700; letter-spacing: 0; margin: 0 0 5px; text-transform: uppercase; }
.report-hero h1 { color: {$ink}; font-size: 24px; line-height: 1.15; margin: 0 0 6px; }
.report-hero p { color: {$muted}; margin: 0; }
.report-snapshot { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid {$accent}; break-inside: avoid; margin-bottom: 16px; padding: 15px 18px; }
.report-snapshot h2 { color: {$accentDark}; font-size: 15px; margin: 0 0 10px; }
.report-meta { border-top: 1px solid #eee7db; display: grid; gap: 12px; grid-template-columns: repeat({$metaColumns}, 1fr); margin: 0; padding: 10px 0 0; }
.report-meta div { min-width: 0; }
.report-meta dt { color: {$muted}; font-size: 8.5px; font-weight: 700; margin: 0 0 2px; text-transform: uppercase; }
.report-meta dd { margin: 0; overflow-wrap: anywhere; }
.report-content { display: grid; gap: 14px; }
.report-section { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid {$accent}; break-inside: avoid; padding: 13px 15px; }
.report-section h2 { color: {$accentDark}; font-size: 15px; line-height: 1.3; margin: 0 0 7px; }
.section-body { white-space: pre-wrap; }
.section-body p { margin: 0 0 8px; }
.chart { margin: 12px 0; }
.evidence { border-top: 1px solid #eee7db; color: {$muted}; font-size: 9.5px; margin-top: 10px; padding-top: 7px; }
.evidence p { margin: 0 0 3px; }
.requirement { border-top: 1px solid #eee7db; padding: 10px 0; }
.requirement:first-of-type { border-top: 0; padding-top: 0; }
.requirement h3 { color: {$ink}; font-size: 13px; margin: 0; }
.status { border-radius: 999px; display: inline-block; font-size: 10px; margin-left: 6px; padding: 2px 7px; }
.complete { background: #e8f5ef; color: #176b4d; }
.pending { background: #fff7e6; color: #945a00; }
.body { margin-top: 6px; white-space: pre-wrap; }
.note { border-top: 1px solid #eee7db; color: {$muted}; font-size: 9.5px; margin: 8px 0 0; padding-top: 6px; }
.missing-panel { background: #fffaf0; border-left-color: #b8860b; }
.missing-panel ul { margin: 0; padding-left: 18px; }
table { border-collapse: collapse; margin: 8px 0 14px; width: 100%; }
th, td { border: 1px solid #d8e2dc; padding: 5px 6px; text-align: right; vertical-align: top; }
th:first-child, td:first-child { text-align: left; }
th { background: #f5f8f6; color: #34443c; font-size: 10px; }
.muted { color: {$muted}; }
.report-footer { border-top: 1px solid #ded6c7; color: {$muted}; font-size: 9px; margin-top: 24px; padding-top: 8px; text-align: right; }
CSS;
    }

    private function templateLayoutColor(?Template $template, string $key, string $default): string
    {
        $value = $template instanceof Template ? data_get($template->structure, 'layout.'.$key) : null;

        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $default;
        }

        return $value;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
