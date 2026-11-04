<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use Illuminate\Support\Str;

/** @phpstan-import-type FactData from FactCodec */
final class PlanFacts
{
    /** @return list<Fact> */
    public function capture(SourceRecords $source): array
    {
        $facts = [];
        $money = '([0-9][0-9,]*(?:\\.[0-9]+)?)';
        foreach ($source->rows('sections') as $section) {
            $body = (string) ($section['body'] ?? '');
            $text = html_entity_decode(strip_tags(Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ref = new SourceReference('plan_section', (string) ($section['id'] ?? ''), (string) data_get($section, 'metadata.requirement_key', $section['key'] ?? ''), CanonicalJson::hash($body));
            foreach ([
                'plan.opening_cash' => ['/(?:(?:opening|starting) cash(?: balance)?|cash balance|currently has)\\s*(?:is|of|:)?\\s*(?:approximately |about )?\\$'.$money.'/i', 'NZD'],
                'plan.owner_pay' => ['/(?:currently (?:allocate|pay)|current owner.{0,15}pay)\\s*(?:is |of |: )?\\$'.$money.'\\s*(?:per |a |\\/)?week/i', 'NZD/week'],
                'plan.profitability' => ['/break[ -]even[^.\\n]{0,70}\\byear\\s+(\\d+)/i', 'year'],
            ] as $key => [$pattern, $unit]) {
                preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $value = str_replace(',', '', $match[1]);
                    $facts[] = new Fact($key, $value, unit: $unit, sources: [$ref]);
                    if ($key === 'plan.profitability') {
                        $facts[] = new Fact('scope.required_horizon', (int) $value * 12, unit: 'months', sources: [$ref]);
                    }
                }
            }
            if (preg_match('/(?:profit before tax|NPBT|net profit)[^.\\n]{0,80}?(\\d+(?:\\.\\d+)?)\\s*[-–]\\s*(\\d+(?:\\.\\d+)?)\\s*%/iu', $text, $margin) === 1) {
                $facts[] = new Fact('plan.margin_min', $margin[1], period: 'unspecified', unit: 'percent', sources: [$ref]);
                $facts[] = new Fact('plan.margin_max', $margin[2], period: 'unspecified', unit: 'percent', sources: [$ref]);
            }
        }
        $latest = [];
        foreach ($source->rows('claims') as $claim) {
            if (is_string($claim['claim_key'] ?? null)) {
                $latest[$claim['claim_key']] = $claim;
            }
        }
        foreach ($latest as $record) {
            if (($record['state'] ?? null) !== 'confirmed' || ! is_array($record['fact'] ?? null)) {
                continue;
            }
            /** @var FactData $data */
            $data = $record['fact'];
            $confirmed = FactCodec::decode($data);
            $current = false;
            foreach ($source->rows('sections') as $section) {
                foreach ($confirmed->sources as $ref) {
                    if ($ref->kind === 'plan_section' && $ref->id === ($section['id'] ?? null)
                        && hash_equals($ref->version, CanonicalJson::hash((string) ($section['body'] ?? '')))) {
                        $current = true;
                    }
                }
            }
            if (! $current) {
                continue;
            }
            // Bind a matching extracted proposal to its confirmed semantic basis.
            // Other rendered versions remain separate and continue to be checked.
            $facts = array_values(array_filter($facts, fn (Fact $f): bool => ! ($f->key === $confirmed->key && $f->value === $confirmed->value
                && $f->unit === $confirmed->unit && array_any($f->sources, fn (SourceReference $r): bool => array_any($confirmed->sources, fn (SourceReference $c): bool => $c->id === $r->id && $c->version === $r->version)))));
            $facts[] = $confirmed;
        }

        return $facts;
    }

    /** @return list<DocumentBlock> */
    public function blocks(SourceRecords $source): array
    {
        $blocks = [];
        foreach ($source->rows('sections') as $section) {
            $body = (string) ($section['body'] ?? '');
            $ref = new SourceReference('plan_section', (string) ($section['id'] ?? ''), (string) data_get($section, 'metadata.requirement_key', $section['key'] ?? ''), CanonicalJson::hash($body));
            $generated = ($section['key'] ?? null) === BusinessPlanExecutiveSummary::SECTION_KEY
                && data_get($section, 'metadata.executive_summary.generated') === true;
            $blocks[] = new DocumentBlock($ref->id.':heading', (string) ($section['title'] ?? ''), $ref, heading: true);
            $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $blocks[] = new DocumentBlock($ref->id.':body', $text, $ref, generated: $generated);
        }

        return $blocks;
    }
}
