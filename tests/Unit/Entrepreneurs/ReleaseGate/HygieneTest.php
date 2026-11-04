<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CandidateBlocks;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DisplayPolicy;
use App\Services\Entrepreneurs\ReleaseGate\DocumentBlock;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\GeneratedNarrative;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HygieneRule;
use PHPUnit\Framework\TestCase;

final class HygieneTest extends TestCase
{
    public function test_display_policy_checks_precision_currency_and_real_calendar_dates(): void
    {
        $policy = DisplayPolicy::from(SyntheticCase::fact('document.display_policy', 'NZD; whole dollars; day month year'));
        self::assertNotNull($policy);
        self::assertTrue($policy->accepts('Available NZD 1,000 on 1 Nov 2026.'));
        foreach (['$1,000.00', 'USD 1000', '$10,00', '2026-11-01', '31 Nov 2026', '01/11/26'] as $bad) {
            self::assertFalse($policy->accepts($bad), $bad);
        }
        $cents = DisplayPolicy::from(SyntheticCase::fact('document.display_policy', 'NZD; two decimals; day month year'));
        self::assertTrue($cents->accepts('Reserve NZ$232.73'));
        self::assertNull(DisplayPolicy::from(SyntheticCase::fact('document.display_policy', 'unspecified')));
    }

    public function test_generated_autofix_preserves_quoted_evidence_and_rechecks_clean_text(): void
    {
        $text = "Generated — narrative. “Quoted — evidence.”\n> Source — extract.\nAnother — sentence. \"Exact — quote\"";
        $fixed = GeneratedNarrative::text($text);
        self::assertSame("Generated - narrative. “Quoted — evidence.”\n> Source — extract.\nAnother - sentence. \"Exact — quote\"", $fixed);
        self::assertSame($fixed, GeneratedNarrative::text($fixed));
        $source = SyntheticCase::fact('source', 'text')->sources[0];
        $base = SyntheticCase::context([]);
        $context = new DocumentContext($base->source, $base->facts, 'brief', Audience::External,
            [new DocumentBlock('generated', 'Generated - narrative.', $source, generated: true), new DocumentBlock('quote', 'Quoted — evidence.', $source, generated: true, quotation: true)], true);
        $checks = (new HygieneRule('DH-06'))->evaluate($context, new RuleConfiguration);
        self::assertSame(CheckStatus::Pass, (new RuleResult(RuleCatalogue::get('DH-06'), true, $checks))->status);
    }

    public function test_hygiene_uses_rendered_markup_and_does_not_flag_repeated_numeric_cells_as_duplicate_narrative(): void
    {
        $blocks = (new CandidateBlocks)->fromHtml('<html><style>.test{display:block}</style><h1>Forecast</h1><p>One day of delivery.</p><table><tr><td>$0</td><td>$0</td></tr></table></html>', 'synthetic', 'primary');
        self::assertCount(4, $blocks);
        $base = SyntheticCase::context([]);
        $context = new DocumentContext($base->source, new FactSet([SyntheticCase::fact('document.display_policy', 'NZD; whole dollars; day month year')]), 'budget', Audience::External, $blocks, true);
        foreach (['DH-01', 'DH-02', 'DH-03', 'DH-04', 'DH-05', 'DH-06'] as $id) {
            self::assertSame(CheckStatus::Pass, (new RuleResult(RuleCatalogue::get($id), true, (new HygieneRule($id))->evaluate($context, new RuleConfiguration)))->status, $id);
        }
        $incomplete = new DocumentContext($base->source, $context->facts, 'budget', Audience::External, $blocks, false);
        self::assertSame(CheckStatus::CannotVerify, (new RuleResult(RuleCatalogue::get('DH-04'), true, (new HygieneRule('DH-04'))->evaluate($incomplete, new RuleConfiguration)))->status);
    }
}
