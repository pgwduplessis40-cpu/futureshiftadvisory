<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

final class PanelAgreementPdfRenderer
{
    public const BRANDED_FALLBACK_MARKER = 'FSA-PANEL-AGREEMENT-BRANDED-FALLBACK-V2';

    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const MARGIN = 44;

    public function __construct(
        private readonly PdfRenderer $renderer,
    ) {}

    public function renderPdf(PanelAgreement $agreement, User $actor, DateTimeInterface $signedAt): string
    {
        try {
            return $this->renderer->render($this->renderHtml($agreement, $actor, $signedAt));
        } catch (Throwable $exception) {
            report($exception);

            return $this->renderBrandedFallbackPdf($agreement, $actor, $signedAt);
        }
    }

    public function renderHtml(PanelAgreement $agreement, User $actor, DateTimeInterface $signedAt): string
    {
        $agreement = $agreement->loadMissing('panelMember.user');
        $member = $agreement->panelMember;
        $terms = $agreement->terms ?? [];
        $title = $this->agreementTitle($agreement);
        $partnerType = $member instanceof PanelMember ? $this->panelTypeLabel($member) : 'Partner';
        $partnerName = $this->partnerName($member, $actor);
        $logo = $this->logoDataUri();

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>%s</style>
</head>
<body>
<template data-pdf-footer>
<div class="pdf-footer"><span>Future Shift Advisory partner agreement</span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>
</template>
<header class="letterhead">
<div class="brand">
%s
<div>
<p class="brand-name">Future Shift Advisory</p>
<p class="brand-line">Mentor - Advisor - Partner</p>
</div>
</div>
<div class="document-tag">Signed agreement</div>
</header>
<main>
<section class="hero">
<p class="eyebrow">%s</p>
<h1>%s</h1>
<p>%s</p>
</section>
<section class="card">
<h2>Agreement parties</h2>
<dl class="meta-grid">
%s
</dl>
</section>
<section class="card">
<h2>Agreement summary</h2>
%s
</section>
%s
%s
<section class="card signature-card">
<h2>Signature certificate</h2>
<p>This certificate records the partner acceptance and the signed agreement evidence retained by Future Shift Advisory.</p>
<dl class="meta-grid">
%s
</dl>
</section>
</main>
</body>
</html>
HTML,
            $this->escape($title),
            $this->css(),
            $logo === null
                ? '<div class="brand-mark"><span></span><span></span><span></span></div>'
                : '<img class="brand-logo" src="'.$this->escape($logo).'" alt="Future Shift Advisory">',
            $this->escape($partnerType),
            $this->escape($title),
            $this->escape((string) ($terms['agreement_introduction'] ?? 'This agreement records the operating terms for approved Future Shift Advisory partners.')),
            $this->metadataHtml([
                'Partner' => $partnerName,
                'Partner type' => $partnerType,
                'Agreement ID' => (string) $agreement->getKey(),
                'Agreement status' => 'Signed',
                'Generated' => $this->dateValue($agreement->generated_at),
                'Signed' => $signedAt->format('j M Y, g:i A T'),
                'Signed by' => $actor->name.' <'.$actor->email.'>',
            ]),
            $this->paragraphs((string) ($terms['standard_terms'] ?? '')),
            $this->standardClausesHtml($terms),
            $this->panelClausesHtml($member, $terms),
            $this->metadataHtml([
                'Signed at' => $signedAt->format('j M Y, g:i A T'),
                'Signed by' => $actor->name.' <'.$actor->email.'>',
                'User ID' => (string) $actor->getKey(),
                'Agreement ID' => (string) $agreement->getKey(),
                'Document note' => 'Private portal credentials are not stored in this agreement certificate.',
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function standardClausesHtml(array $terms): string
    {
        $items = [
            'Future Shift Advisory and the partner must protect confidential client and platform information.',
            'Client referral information may only be shared where the relevant client consent has been obtained.',
            (string) ($terms['mutual_referral_terms'] ?? 'No referral fees are payable by either party unless separately agreed in writing.'),
            'Reverse referrals do not give the partner automatic access to client records or advisory workspaces.',
        ];

        return $this->clauseSection('Standard partner terms', $items);
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function panelClausesHtml(?PanelMember $member, array $terms): string
    {
        if (! $member instanceof PanelMember) {
            return '';
        }

        if ($member->panel_type === PanelMember::TYPE_BROKER) {
            $clauses = is_array($terms['broker_clauses'] ?? null) ? $terms['broker_clauses'] : [];

            return $this->clauseSection('Broker operating terms', array_values(array_filter([
                'FSP registration recorded for this agreement: '.$this->scalar($clauses['fsp_number'] ?? $member->fsp_number ?? 'Not recorded').'.',
                'FSP status at approval: '.$this->scalar($clauses['fsp_status_at_approval'] ?? $member->fsp_status ?? 'Not recorded').'.',
                'The broker must keep their FSP registration current. A lapsed or non-current FSP status may suspend portal access until resolved.',
                'The broker remains responsible for regulated financial advice and any client advice obligations outside the Future Shift Advisory platform.',
                'Client consent is required before broker referral context or client information is shared.',
                ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                    'regulated financial advice',
                    'fsp registration current',
                    'lapsed or non-current fsp status',
                ]),
            ])));
        }

        if ($member->panel_type === PanelMember::TYPE_COACH) {
            $clauses = is_array($terms['coach_clauses'] ?? null) ? $terms['coach_clauses'] : [];
            $specialisations = $clauses['specialisations'] ?? $member->coach_specialisations ?? [];
            $specialisationText = is_array($specialisations) && $specialisations !== []
                ? implode(', ', array_map(fn (mixed $value): string => $this->scalar($value), $specialisations))
                : 'Not recorded';

            return $this->clauseSection('Coach operating terms', array_values(array_filter([
                'Approved coaching specialisations: '.$specialisationText.'.',
                'Professional memberships may be displayed where they are held and verified.',
                $this->scalar($clauses['wellbeing_scope_boundary'] ?? 'Coaching support only; no clinical mental-health diagnosis, treatment, crisis support, or regulated health advice.'),
                'Client authorisation is required before key-staff coaching context is shared.',
                'Entrepreneur referrals must keep the relevant profile link and referral context attached.',
                ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                    'clinical mental-health',
                    'client authorisation',
                ]),
            ])));
        }

        return '';
    }

    /**
     * @param  array<int, string>  $items
     */
    private function clauseSection(string $title, array $items): string
    {
        $list = collect($items)
            ->map(fn (string $item): string => '<li>'.$this->escape($item).'</li>')
            ->implode('');

        return sprintf(
            '<section class="card"><h2>%s</h2><ul class="clauses">%s</ul></section>',
            $this->escape($title),
            $list,
        );
    }

    /**
     * @param  array<string, string>  $items
     */
    private function metadataHtml(array $items): string
    {
        return collect($items)
            ->map(fn (string $value, string $label): string => sprintf(
                '<div><dt>%s</dt><dd>%s</dd></div>',
                $this->escape($label),
                $this->escape($value),
            ))
            ->implode('');
    }

    private function paragraphs(string $text): string
    {
        $lines = $this->textLines($text);

        if ($lines === []) {
            return '<p>Partners must protect confidential information, act within their authorised scope, and obtain client consent before referral information is shared.</p>';
        }

        return collect($lines)
            ->map(fn (string $line): string => '<p>'.$this->escape($line).'</p>')
            ->implode('');
    }

    /**
     * @return array<int, string>
     */
    private function textLines(mixed $value): array
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return [];
        }

        return collect(preg_split('/\R+/', (string) $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $duplicateNeedles
     * @return array<int, string>
     */
    private function additionalAdminLines(mixed $value, array $duplicateNeedles): array
    {
        return collect($this->textLines($value))
            ->reject(function (string $line) use ($duplicateNeedles): bool {
                $line = Str::lower($line);

                return collect($duplicateNeedles)
                    ->contains(fn (string $needle): bool => str_contains($line, Str::lower($needle)));
            })
            ->values()
            ->all();
    }

    private function renderBrandedFallbackPdf(PanelAgreement $agreement, User $actor, DateTimeInterface $signedAt): string
    {
        $agreement = $agreement->loadMissing('panelMember.user');
        $member = $agreement->panelMember;
        $title = $this->agreementTitle($agreement);
        $partnerType = $member instanceof PanelMember ? $this->panelTypeLabel($member) : 'Partner';
        $partnerName = $this->partnerName($member, $actor);
        $intro = (string) (($agreement->terms ?? [])['agreement_introduction'] ?? 'This agreement records the operating terms for approved Future Shift Advisory partners.');
        $pages = [];
        $ops = [];
        $y = 0;
        $pageNumber = 0;

        $newPage = function () use (&$pages, &$ops, &$y, &$pageNumber): void {
            if ($ops !== []) {
                $this->drawFooter($ops, $pageNumber);
                $pages[] = implode("\n", $ops)."\n";
            }

            $pageNumber++;
            $ops = [];
            $this->drawFallbackLetterhead($ops);
            $y = 678;
        };

        $newPage();
        $y = $this->drawHero($ops, $title, $partnerType, $intro, $partnerName);
        $partyItems = $this->fallbackPartyItems($agreement, $member, $actor, $signedAt);
        $termSections = $this->fallbackTermSections($member, $agreement->terms ?? []);
        $signatureItems = $this->fallbackSignatureItems($actor, $signedAt);

        $partyHeight = $this->metadataCardHeight($partyItems);
        if ($y - $partyHeight < 66) {
            $newPage();
        }
        $y = $this->drawMetadataCard($ops, 'Agreement snapshot', $partyItems, $y) - 10;

        $termsHeight = $this->termsCardHeight($termSections);
        if ($y - $termsHeight < 66) {
            $newPage();
        }
        $y = $this->drawTermsCard($ops, 'Operating terms', $termSections, $y) - 10;

        $signatureHeight = $this->signatureCardHeight($signatureItems);
        if ($y - $signatureHeight < 66) {
            $newPage();
        }
        $this->drawSignatureCard($ops, $signatureItems, $y);

        $this->drawFooter($ops, $pageNumber);
        $pages[] = implode("\n", $ops)."\n";

        return $this->buildPdf($pages);
    }

    /**
     * @return array<string, string>
     */
    private function fallbackPartyItems(PanelAgreement $agreement, ?PanelMember $member, User $actor, DateTimeInterface $signedAt): array
    {
        return [
            'Partner' => $this->partnerName($member, $actor),
            'Partner type' => $member instanceof PanelMember ? $this->panelTypeLabel($member) : 'Partner',
            'Agreement status' => 'Signed',
            'Agreement ID' => (string) $agreement->getKey(),
            'Generated' => $this->dateValue($agreement->generated_at),
            'Signed' => $signedAt->format('j M Y, g:i A T'),
        ];
    }

    /**
     * @param  array<string, mixed>  $terms
     * @return array<int, array{title:string,items:array<int, string>}>
     */
    private function fallbackTermSections(?PanelMember $member, array $terms): array
    {
        $sections = [[
            'title' => 'Standard partner terms',
            'items' => [
                'Future Shift Advisory and the partner must protect confidential client and platform information.',
                'Client referral information may only be shared where the relevant client consent has been obtained.',
                (string) ($terms['mutual_referral_terms'] ?? 'No referral fees are payable by either party unless separately agreed in writing.'),
                'Reverse referrals do not give the partner automatic access to client records or advisory workspaces.',
            ],
        ]];

        if ($member instanceof PanelMember && $member->panel_type === PanelMember::TYPE_BROKER) {
            $clauses = is_array($terms['broker_clauses'] ?? null) ? $terms['broker_clauses'] : [];
            $items = [
                'FSP registration recorded for this agreement: '.$this->scalar($clauses['fsp_number'] ?? $member->fsp_number ?? 'Not recorded').'.',
                'FSP status at approval: '.$this->scalar($clauses['fsp_status_at_approval'] ?? $member->fsp_status ?? 'Not recorded').'.',
                'The broker must keep their FSP registration current. A lapsed or non-current FSP status may suspend portal access until resolved.',
                'The broker remains responsible for regulated financial advice and any client advice obligations outside the Future Shift Advisory platform.',
                'Client consent is required before broker referral context or client information is shared.',
            ];

            array_push($items, ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                'regulated financial advice',
                'fsp registration current',
                'lapsed or non-current fsp status',
            ]));

            $sections[] = [
                'title' => 'Broker operating terms',
                'items' => $items,
            ];
        }

        if ($member instanceof PanelMember && $member->panel_type === PanelMember::TYPE_COACH) {
            $clauses = is_array($terms['coach_clauses'] ?? null) ? $terms['coach_clauses'] : [];
            $items = [
                $this->scalar($clauses['wellbeing_scope_boundary'] ?? 'Coaching support only; no clinical mental-health diagnosis, treatment, crisis support, or regulated health advice.'),
                'Client authorisation is required before key-staff coaching context is shared.',
                'Entrepreneur referrals must keep the relevant profile link and referral context attached.',
            ];

            array_push($items, ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                'clinical mental-health',
                'client authorisation',
            ]));

            $sections[] = [
                'title' => 'Coach operating terms',
                'items' => $items,
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, string>
     */
    private function fallbackSignatureItems(User $actor, DateTimeInterface $signedAt): array
    {
        return [
            'Signed at' => $signedAt->format('j M Y, g:i A T'),
            'Signed by' => $actor->name.' <'.$actor->email.'>',
            'User ID' => (string) $actor->getKey(),
            'Document note' => 'Private portal credentials are not stored in this agreement certificate.',
        ];
    }

    /**
     * @param  array<string, string>  $items
     */
    private function metadataCardHeight(array $items): int
    {
        return 42 + ((int) ceil(count($items) / 2)) * 27;
    }

    /**
     * @param  array<int, array{title:string,items:array<int, string>}>  $sections
     */
    private function termsCardHeight(array $sections): int
    {
        $height = 44;

        foreach ($sections as $section) {
            $height += 17;

            foreach ($section['items'] as $item) {
                $height += max(1, count($this->wrapPdfText($item, 82))) * 11 + 6;
            }

            $height += 5;
        }

        return $height;
    }

    /**
     * @param  array<string, string>  $items
     */
    private function signatureCardHeight(array $items): int
    {
        $height = 62;

        foreach ($items as $value) {
            $height += max(1, count($this->wrapPdfText($value, 82))) * 11 + 5;
        }

        return $height;
    }

    /**
     * @param  array<int, string>  $ops
     * @param  array<string, string>  $items
     */
    private function drawMetadataCard(array &$ops, string $title, array $items, int $topY): int
    {
        $x = self::MARGIN;
        $width = self::PAGE_WIDTH - (self::MARGIN * 2);
        $height = $this->metadataCardHeight($items);
        $this->drawCardFrame($ops, $x, $topY, $width, $height);
        $this->pdfRect($ops, $x, $topY - $height, 4, $height, '#0d7a7a');
        $this->pdfText($ops, $title, $x + 18, $topY - 23, 12, 'F2', '#1c2f4a');
        $this->pdfLine($ops, $x + 18, $topY - 35, $x + $width - 18, $topY - 35, '#eee7db', 0.6);

        $columnWidth = ($width - 48) / 2;
        $rowY = $topY - 52;
        $index = 0;

        foreach ($items as $label => $value) {
            $column = $index % 2;
            $row = intdiv($index, 2);
            $itemX = $x + 18 + ($column * ($columnWidth + 12));
            $itemY = $rowY - ($row * 27);

            $this->pdfText($ops, Str::upper($label), $itemX, $itemY + 8, 7.2, 'F2', '#667282');
            $this->pdfText($ops, $value, $itemX, $itemY - 5, 9, 'F1', '#13233a');

            $index++;
        }

        return $topY - $height;
    }

    /**
     * @param  array<int, string>  $ops
     * @param  array<int, array{title:string,items:array<int, string>}>  $sections
     */
    private function drawTermsCard(array &$ops, string $title, array $sections, int $topY): int
    {
        $x = self::MARGIN;
        $width = self::PAGE_WIDTH - (self::MARGIN * 2);
        $height = $this->termsCardHeight($sections);
        $this->drawCardFrame($ops, $x, $topY, $width, $height);
        $this->pdfText($ops, $title, $x + 18, $topY - 23, 12, 'F2', '#1c2f4a');
        $this->pdfText($ops, 'Approved partner obligations and access boundaries', $x + 308, $topY - 23, 8.5, 'F1', '#667282');
        $this->pdfLine($ops, $x + 18, $topY - 35, $x + $width - 18, $topY - 35, '#eee7db', 0.6);

        $y = $topY - 53;

        foreach ($sections as $sectionIndex => $section) {
            if ($sectionIndex > 0) {
                $this->pdfLine($ops, $x + 18, $y + 8, $x + $width - 18, $y + 8, '#eee7db', 0.5);
                $y -= 5;
            }

            $this->pdfText($ops, Str::upper($section['title']), $x + 18, $y, 8.4, 'F2', '#0d6a5a');
            $y -= 16;

            foreach ($section['items'] as $item) {
                $wrapped = $this->wrapPdfText($item, 82);
                $this->pdfRect($ops, $x + 20, $y + 3, 3, 3, '#b8860b');

                foreach ($wrapped as $lineIndex => $line) {
                    $this->pdfText($ops, $line, $x + 30, $y - ($lineIndex * 11), 8.8, 'F1', '#13233a');
                }

                $y -= max(1, count($wrapped)) * 11 + 6;
            }
        }

        return $topY - $height;
    }

    /**
     * @param  array<int, string>  $ops
     * @param  array<string, string>  $items
     */
    private function drawSignatureCard(array &$ops, array $items, int $topY): int
    {
        $x = self::MARGIN;
        $width = self::PAGE_WIDTH - (self::MARGIN * 2);
        $height = $this->signatureCardHeight($items);
        $this->pdfRect($ops, $x, $topY - $height, $width, $height, '#f8f5ee', '#ded6c7');
        $this->pdfRect($ops, $x, $topY - $height, 4, $height, '#b8860b');
        $this->pdfText($ops, 'Signature certificate', $x + 18, $topY - 24, 12, 'F2', '#1c2f4a');
        $this->pdfRect($ops, $x + $width - 82, $topY - 31, 58, 18, '#1c2f4a');
        $this->pdfText($ops, 'SIGNED', $x + $width - 68, $topY - 25, 8, 'F2', '#ffffff');
        $this->pdfText($ops, 'Acceptance evidence retained by Future Shift Advisory.', $x + 18, $topY - 43, 8.8, 'F1', '#435466');

        $y = $topY - 62;

        foreach ($items as $label => $value) {
            $this->pdfText($ops, Str::upper($label), $x + 18, $y + 7, 7.1, 'F2', '#667282');
            $wrapped = $this->wrapPdfText($value, 82);

            foreach ($wrapped as $lineIndex => $line) {
                $this->pdfText($ops, $line, $x + 116, $y + 7 - ($lineIndex * 11), 8.8, 'F1', '#13233a');
            }

            $y -= max(1, count($wrapped)) * 11 + 5;
        }

        return $topY - $height;
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function drawCardFrame(array &$ops, int $x, int $topY, int $width, int $height): void
    {
        $this->pdfRect($ops, $x, $topY - $height, $width, $height, '#ffffff', '#ded6c7');
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function drawFallbackLetterhead(array &$ops): void
    {
        $this->pdfRect($ops, 0, 806, self::PAGE_WIDTH, 36, '#1c2f4a');
        $this->pdfRect($ops, 0, 802, self::PAGE_WIDTH, 4, '#b8860b');
        $this->pdfText($ops, 'Mentor', 291, 819, 10, 'F2', '#d4a020');
        $this->pdfText($ops, 'Advisor', 338, 819, 10, 'F2', '#d4a020');
        $this->pdfText($ops, 'Partner', 390, 819, 10, 'F2', '#d4a020');

        $this->pdfRect($ops, self::MARGIN, 746, 138, 40, '#ffffff', '#ded6c7');
        $this->pdfRect($ops, self::MARGIN + 12, 756, 7, 13, '#4a6a8a');
        $this->pdfRect($ops, self::MARGIN + 22, 756, 7, 21, '#1b5070');
        $this->pdfRect($ops, self::MARGIN + 32, 756, 7, 29, '#0d7a7a');
        $this->pdfRect($ops, self::MARGIN + 42, 756, 7, 35, '#0d6a5a');
        $this->pdfLine($ops, self::MARGIN + 12, 761, self::MARGIN + 49, 786, '#b8860b', 1.1);
        $this->pdfText($ops, 'Future Shift', self::MARGIN + 58, 773, 9.5, 'F2', '#1c2f4a');
        $this->pdfText($ops, 'ADVISORY', self::MARGIN + 58, 762, 6.5, 'F2', '#5a7a70');
        $this->pdfLine($ops, self::MARGIN + 58, 770, self::MARGIN + 125, 770, '#d8b15a', 0.5);

        $this->pdfRect($ops, 426, 759, 121, 22, '#f4efe3', '#d8d1c2');
        $this->pdfText($ops, 'Signed agreement', 444, 766, 9, 'F2', '#1c2f4a');
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function drawHero(array &$ops, string $title, string $partnerType, string $intro, string $partnerName): int
    {
        $this->pdfRect($ops, self::MARGIN, 676, 507, 58, '#f8f5ee', '#ded6c7');
        $this->pdfRect($ops, self::MARGIN, 676, 5, 58, '#b8860b');
        $this->pdfText($ops, Str::upper($partnerType), self::MARGIN + 18, 716, 8, 'F2', '#0d6a5a');
        $this->pdfText($ops, $title, self::MARGIN + 18, 698, 19, 'F2', '#13233a');
        $this->pdfText($ops, $partnerName, self::MARGIN + 18, 683, 10, 'F2', '#13233a');

        $summary = $this->wrapPdfText($intro, 104)[0] ?? '';
        $this->pdfText($ops, $summary, self::MARGIN, 660, 8.8, 'F1', '#435466');

        return 644;
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function drawFooter(array &$ops, int $pageNumber): void
    {
        $this->pdfLine($ops, self::MARGIN, 56, self::PAGE_WIDTH - self::MARGIN, 56, '#ded6c7', 0.6);
        $this->pdfText($ops, 'Future Shift Advisory partner agreement', self::MARGIN, 38, 8, 'F1', '#667282');
        $this->pdfText($ops, 'Page '.$pageNumber, self::PAGE_WIDTH - self::MARGIN - 38, 38, 8, 'F1', '#667282');
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function pdfRect(array &$ops, int|float $x, int|float $y, int|float $width, int|float $height, string $fill, ?string $stroke = null): void
    {
        $ops[] = 'q';
        $ops[] = $this->pdfColor($fill, 'rg');
        $ops[] = $stroke !== null ? $this->pdfColor($stroke, 'RG') : '';
        $ops[] = sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $width, $height, $stroke === null ? 'f' : 'B');
        $ops[] = 'Q';
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function pdfLine(array &$ops, int|float $x1, int|float $y1, int|float $x2, int|float $y2, string $color, float $width): void
    {
        $ops[] = 'q';
        $ops[] = $this->pdfColor($color, 'RG');
        $ops[] = sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $width, $x1, $y1, $x2, $y2);
        $ops[] = 'Q';
    }

    /**
     * @param  array<int, string>  $ops
     */
    private function pdfText(array &$ops, string $text, int|float $x, int|float $y, int|float $size, string $font, string $color): void
    {
        $ops[] = 'BT';
        $ops[] = '/'.$font.' '.sprintf('%.2F', $size).' Tf';
        $ops[] = $this->pdfColor($color, 'rg');
        $ops[] = sprintf('1 0 0 1 %.2F %.2F Tm', $x, $y);
        $ops[] = '('.$this->pdfEscape($text).') Tj';
        $ops[] = 'ET';
    }

    private function pdfColor(string $hex, string $operator): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        return sprintf('%.4F %.4F %.4F %s', $r, $g, $b, $operator);
    }

    /**
     * @return array<int, string>
     */
    private function wrapPdfText(string $text, int $characters): array
    {
        return explode("\n", wordwrap($this->normalisePdfText($text), $characters, "\n", true));
    }

    private function normalisePdfText(string $text): string
    {
        $ascii = Str::ascii($text);

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $ascii) ?? '';
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->normalisePdfText($text));
    }

    /**
     * @param  array<int, string>  $pages
     */
    private function buildPdf(array $pages): string
    {
        $objectCount = 2 + (count($pages) * 2) + 2;
        $regularFontObjectId = $objectCount - 1;
        $boldFontObjectId = $objectCount;
        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        foreach ($pages as $index => $content) {
            $pageObjectId = 3 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = "{$pageObjectId} 0 R";

            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $regularFontObjectId,
                $boldFontObjectId,
                $contentObjectId,
            );
            $objects[$contentObjectId] = '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        }

        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($pages));
        $objects[$regularFontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%".self::BRANDED_FALLBACK_MARKER."\n";
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

    private function agreementTitle(PanelAgreement $agreement): string
    {
        $title = (string) (($agreement->terms ?? [])['agreement_title'] ?? 'Future Shift Advisory partner agreement');

        return Str::headline($title);
    }

    private function panelTypeLabel(PanelMember $member): string
    {
        return match ($member->panel_type) {
            PanelMember::TYPE_BROKER => 'Broker partner',
            PanelMember::TYPE_COACH => 'Coach partner',
            default => 'Partner',
        };
    }

    private function partnerName(?PanelMember $member, User $actor): string
    {
        $application = $member instanceof PanelMember && is_array($member->application) ? $member->application : [];
        $company = $application['company'] ?? $application['trading_name'] ?? $application['broker_name'] ?? null;

        if (is_scalar($company) && trim((string) $company) !== '') {
            return (string) $company;
        }

        return $actor->name;
    }

    private function dateValue(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('j M Y, g:i A T');
        }

        return 'Not recorded';
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value) !== '' ? (string) $value : 'Not recorded';
        }

        return 'Not recorded';
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('brand-assets/future-shift-advisory-logo.svg');

        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if (! is_string($content) || $content === '') {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($content);
    }

    private function css(): string
    {
        return <<<'CSS'
@page { margin: 15mm 15mm 20mm; }
* { box-sizing: border-box; }
body {
  background: #ffffff;
  color: #13233a;
  font-family: Arial, sans-serif;
  font-size: 11.5px;
  line-height: 1.55;
  margin: 0;
}
.letterhead {
  align-items: center;
  border-top: 7px solid #1c2f4a;
  border-bottom: 1px solid #d8d1c2;
  display: flex;
  justify-content: space-between;
  margin-bottom: 18px;
  padding: 13px 0 12px;
}
.brand {
  align-items: center;
  display: flex;
  gap: 13px;
}
.brand-logo {
  display: block;
  height: 44px;
  width: 158px;
}
.brand-mark {
  align-items: end;
  display: inline-flex;
  gap: 3px;
  height: 36px;
  width: 38px;
}
.brand-mark span {
  background: #0d7a7a;
  border-radius: 1px 1px 0 0;
  display: block;
  width: 8px;
}
.brand-mark span:nth-child(1) { height: 14px; opacity: .55; }
.brand-mark span:nth-child(2) { height: 24px; opacity: .78; }
.brand-mark span:nth-child(3) { height: 34px; }
.brand-name {
  color: #1c2f4a;
  font-size: 15px;
  font-weight: 700;
  margin: 0;
}
.brand-line {
  color: #b8860b;
  font-size: 10px;
  font-weight: 700;
  margin: 2px 0 0;
}
.document-tag {
  background: #f4efe3;
  border: 1px solid #d8d1c2;
  border-radius: 999px;
  color: #1c2f4a;
  font-size: 10px;
  font-weight: 700;
  padding: 5px 11px;
}
.hero {
  background: #f8f5ee;
  border: 1px solid #ded6c7;
  border-left: 5px solid #b8860b;
  margin-bottom: 15px;
  padding: 18px 19px;
}
.eyebrow {
  color: #0d6a5a;
  font-size: 10px;
  font-weight: 700;
  margin: 0 0 4px;
  text-transform: uppercase;
}
h1 {
  color: #13233a;
  font-size: 25px;
  line-height: 1.15;
  margin: 0 0 7px;
}
h2 {
  color: #1c2f4a;
  font-size: 14px;
  margin: 0 0 8px;
}
p {
  margin: 0 0 8px;
}
.card {
  background: #ffffff;
  border: 1px solid #ded6c7;
  break-inside: avoid;
  margin-bottom: 13px;
  padding: 14px 15px;
}
.signature-card {
  border-left: 5px solid #0d6a5a;
}
.meta-grid {
  display: grid;
  gap: 0 18px;
  grid-template-columns: 1fr 1fr;
  margin: 0;
}
.meta-grid div {
  border-top: 1px solid #eee7db;
  min-width: 0;
  padding: 7px 0;
}
.meta-grid div:nth-child(-n+2) {
  border-top: 0;
}
dt {
  color: #596b79;
  font-size: 9.5px;
  font-weight: 700;
  margin: 0 0 2px;
  text-transform: uppercase;
}
dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.clauses {
  margin: 0;
  padding-left: 18px;
}
.clauses li {
  margin: 0 0 7px;
  padding-left: 2px;
}
.pdf-footer {
  align-items: center;
  color: #667282;
  display: flex;
  font-family: Arial, sans-serif;
  font-size: 8px;
  justify-content: space-between;
  padding: 0 15mm;
  width: 100%;
}
CSS;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
