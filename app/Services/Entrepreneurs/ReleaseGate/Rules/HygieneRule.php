<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DisplayPolicy;
use App\Services\Entrepreneurs\ReleaseGate\DocumentBlock;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

/** @phpstan-type TextFingerprint array{text:string,heading:bool,length:int,grams:array<string,int>,count:int} */
final readonly class HygieneRule implements Rule
{
    public function __construct(private string $ruleId)
    {
        if (! in_array($ruleId, ['DH-01', 'DH-02', 'DH-03', 'DH-04', 'DH-05', 'DH-06'], true)) {
            throw new \InvalidArgumentException('Unknown hygiene rule.');
        }
    }

    public function id(): string
    {
        return $this->ruleId;
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $severity = RuleCatalogue::get($this->ruleId)->severity;
        $checks = [];
        $seen = [];
        $display = DisplayPolicy::from($context->facts->one('document.display_policy'));
        foreach ($context->blocks as $block) {
            $fingerprint = $this->ruleId === 'DH-03' ? $this->fingerprint($block) : null;
            $problem = match ($this->ruleId) {
                'DH-01' => $block->internalInstruction || preg_match('/\b(?:record the (?:bank|source)|advisor[- ]entry|enter (?:your )?review note)\b/i', $block->text) === 1,
                'DH-02' => preg_match('/\b(?:UPDATEed|UPDATED|TBD)\b|_{3,}|\[Extract; see full source\.\]/u', $block->text) === 1,
                'DH-03' => $fingerprint !== null && $this->duplicate($fingerprint, $seen, (float) $configuration->threshold('duplicate_similarity_percent')),
                'DH-04' => preg_match('/\*\*[^\n]+\*\*|^\s*#{1,6}\s|\[[^\]]+\]\([^\)]+\)/m', $block->text) === 1,
                'DH-05' => preg_match('/\b1\s+(?:days|weeks|months|years|people|units)\b/i', $block->text) === 1
                    || ($display !== null && ! $display->accepts($block->text)),
                'DH-06' => $block->generated && ! $block->quotation && str_contains($block->text, '—'),
                default => throw new \LogicException('Unknown hygiene rule.'),
            };
            if ($fingerprint !== null) {
                $seen[] = $fingerprint;
            }
            $checks[] = new Subcheck('block:'.$block->id, $problem ? CheckStatus::Fail : CheckStatus::Pass, $severity,
                $problem ? $this->message().' ('.$block->source->location.').' : 'This text block passes '.$this->ruleId.'.',
                [new Fact('document.text', $block->text, entity: $block->id, sources: [$block->source])]);
        }
        if (! $context->renderedTextComplete || $context->blocks === []) {
            $checks[] = Subcheck::missing('rendered-content', 'complete rendered text from the selected renderer', $severity);
        }
        if ($this->ruleId === 'DH-05') {
            if ($display === null) {
                $checks[] = Subcheck::missing('display-policy', 'the shared currency, precision and date display policy', $severity);
            }
        }

        return $checks;
    }

    /** @return TextFingerprint */
    private function fingerprint(DocumentBlock $block): array
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $block->text) ?? $block->text));
        $characters = mb_str_split($text);
        $length = count($characters);
        $grams = [];
        if (! $block->heading && $length >= 120) {
            for ($i = 0; $i < $length - 2; $i++) {
                $gram = $characters[$i].$characters[$i + 1].$characters[$i + 2];
                $grams[$gram] = ($grams[$gram] ?? 0) + 1;
            }
        }

        return ['text' => $text, 'heading' => $block->heading, 'length' => $length, 'grams' => $grams, 'count' => array_sum($grams)];
    }

    /** @param TextFingerprint $block
     * @param  list<TextFingerprint>  $seen
     */
    private function duplicate(array $block, array $seen, float $threshold): bool
    {
        if (! $block['heading'] && $block['length'] < 80) {
            return false;
        }
        foreach ($seen as $previous) {
            if ($previous['heading'] !== $block['heading']) {
                continue;
            }
            if ($block['text'] !== '' && $block['text'] === $previous['text']) {
                return true;
            }
            if (! $block['heading'] && $block['count'] > 0 && $previous['count'] > 0) {
                // Multiset Dice similarity over full Unicode character trigrams.
                // Cache each fingerprint once; never run recursive similar_text
                // on every pair of long plan sections during a page request.
                $total = $block['count'] + $previous['count'];
                if (200 * min($block['count'], $previous['count']) < $threshold * $total) {
                    continue;
                }
                $overlap = 0;
                foreach ($block['grams'] as $gram => $count) {
                    $overlap += min($count, $previous['grams'][$gram] ?? 0);
                }
                $similarity = 200 * $overlap / $total;
                if ($similarity >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    private function message(): string
    {
        return match ($this->ruleId) {
            'DH-01' => 'Remove advisor-entry instructions from external output',
            'DH-02' => 'Resolve the update, missing-text or superseded-version marker',
            'DH-03' => 'Consolidate duplicated headings or near-duplicate text',
            'DH-04' => 'Render the Markdown formatting rather than its raw markers',
            'DH-05' => 'Use the correct singular form and shared display conventions',
            'DH-06' => 'Replace generated em dashes with hyphens, then render and check again',
            default => throw new \LogicException('Unknown hygiene rule.'),
        };
    }
}
