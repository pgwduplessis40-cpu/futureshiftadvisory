<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\SimpleTextPdf;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

final class PanelAgreementPdfRenderer
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly SimpleTextPdf $fallbackPdf,
    ) {}

    public function renderPdf(PanelAgreement $agreement, User $actor, DateTimeInterface $signedAt): string
    {
        try {
            return $this->renderer->render($this->renderHtml($agreement, $actor, $signedAt));
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackPdf->render($this->agreementTitle($agreement), $this->plainTextLines($agreement, $actor, $signedAt));
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

    /**
     * @return array<int, string>
     */
    private function plainTextLines(PanelAgreement $agreement, User $actor, DateTimeInterface $signedAt): array
    {
        $agreement = $agreement->loadMissing('panelMember.user');
        $member = $agreement->panelMember;
        $terms = $agreement->terms ?? [];
        $lines = [
            'Future Shift Advisory partner agreement.',
            'Partner: '.$this->partnerName($member, $actor),
            'Partner type: '.($member instanceof PanelMember ? $this->panelTypeLabel($member) : 'Partner'),
            'Agreement ID: '.$agreement->getKey(),
            'Generated: '.$this->dateValue($agreement->generated_at),
            'Signed at: '.$signedAt->format(DATE_ATOM),
            'Signed by: '.$actor->name.' <'.$actor->email.'>',
            '',
            'Agreement summary:',
            (string) ($terms['agreement_introduction'] ?? 'This agreement records the operating terms for approved Future Shift Advisory partners.'),
            '',
            'Standard partner terms:',
            ...$this->textLines((string) ($terms['standard_terms'] ?? '')),
            (string) ($terms['mutual_referral_terms'] ?? 'No referral fees are payable by either party unless separately agreed in writing.'),
        ];

        if ($member instanceof PanelMember && $member->panel_type === PanelMember::TYPE_BROKER) {
            $clauses = is_array($terms['broker_clauses'] ?? null) ? $terms['broker_clauses'] : [];
            array_push(
                $lines,
                '',
                'Broker operating terms:',
                'FSP registration: '.$this->scalar($clauses['fsp_number'] ?? $member->fsp_number ?? 'Not recorded'),
                'FSP status at approval: '.$this->scalar($clauses['fsp_status_at_approval'] ?? $member->fsp_status ?? 'Not recorded'),
                'The broker must keep their FSP registration current and remains responsible for regulated financial advice.',
                'Client consent is required before broker referral context or client information is shared.',
                ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                    'regulated financial advice',
                    'fsp registration current',
                    'lapsed or non-current fsp status',
                ]),
            );
        }

        if ($member instanceof PanelMember && $member->panel_type === PanelMember::TYPE_COACH) {
            $clauses = is_array($terms['coach_clauses'] ?? null) ? $terms['coach_clauses'] : [];
            array_push(
                $lines,
                '',
                'Coach operating terms:',
                $this->scalar($clauses['wellbeing_scope_boundary'] ?? 'Coaching support only; no clinical mental-health diagnosis, treatment, crisis support, or regulated health advice.'),
                'Client authorisation is required before key-staff coaching context is shared.',
                ...$this->additionalAdminLines($clauses['admin_terms'] ?? null, [
                    'clinical mental-health',
                    'client authorisation',
                ]),
            );
        }

        return array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));
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
