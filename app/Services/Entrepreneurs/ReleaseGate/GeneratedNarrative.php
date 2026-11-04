<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\PlanSection;
use App\Services\Audit\AuditWriter;

final readonly class GeneratedNarrative
{
    public function __construct(private AuditWriter $audit) {}

    public function body(PlanSection $section): string
    {
        $before = (string) $section->body;
        if (data_get($section->metadata, 'executive_summary.generated') !== true
            || data_get($section->metadata, 'quoted_evidence') === true) {
            return $before;
        }
        $after = self::text($before);
        if ($after !== $before && $section->exists) {
            $this->audit->record('entrepreneur.generated_text_autofixed', subject: $section->businessPlan, actor: auth()->user(), after: [
                'rule_id' => 'DH-06', 'section_id' => $section->getKey(), 'before_sha256' => hash('sha256', $before),
                'after_sha256' => hash('sha256', $after), 'transformation' => 'generated_em_dash_to_hyphen', 'stored_answer_changed' => false,
            ]);
        }

        return $after;
    }

    public static function text(string $text): string
    {
        // Preserve quoted source material and Markdown evidence quotations.
        $parts = preg_split('/("[^"\r\n]*"|“[^”]*”|^\h*>[^\r\n]*|<blockquote\b[^>]*>.*?<\/blockquote>)/ums', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = str_replace('—', '-', $part);
            }
        }

        return implode('', $parts);
    }
}
