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
            $problem = match ($this->ruleId) {
                'DH-01' => $block->internalInstruction || preg_match('/\b(?:record the (?:bank|source)|advisor[- ]entry|enter (?:your )?review note)\b/i', $block->text) === 1,
                'DH-02' => preg_match('/\b(?:UPDATEed|UPDATED|TBD)\b|_{3,}|\[Extract; see full source\.\]/u', $block->text) === 1,
                'DH-03' => $this->duplicate($block, $seen, (float) $configuration->threshold('duplicate_similarity_percent')),
                'DH-04' => preg_match('/\*\*[^\n]+\*\*|^\s*#{1,6}\s|\[[^\]]+\]\([^\)]+\)/m', $block->text) === 1,
                'DH-05' => preg_match('/\b1\s+(?:days|weeks|months|years|people|units)\b/i', $block->text) === 1
                    || ($display !== null && ! $display->accepts($block->text)),
                'DH-06' => $block->generated && ! $block->quotation && str_contains($block->text, '—'),
                default => throw new \LogicException('Unknown hygiene rule.'),
            };
            $seen[] = $block;
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

    /** @param list<DocumentBlock> $seen */
    private function duplicate(DocumentBlock $block, array $seen, float $threshold): bool
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $block->text) ?? $block->text));
        if (! $block->heading && mb_strlen($text) < 80) {
            return false;
        }
        foreach ($seen as $previous) {
            if ($previous->heading !== $block->heading) {
                continue;
            }
            $other = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $previous->text) ?? $previous->text));
            if ($text !== '' && $text === $other) {
                return true;
            }
            if (! $block->heading && mb_strlen($text) >= 120 && mb_strlen($other) >= 120) {
                similar_text(mb_substr($text, 0, 4000), mb_substr($other, 0, 4000), $similarity);
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
