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
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use PHPUnit\Framework\TestCase;

final class HygieneTest extends TestCase
{
    public function test_duplicate_review_preserves_headings_short_cells_and_near_duplicate_unicode_text(): void
    {
        $text = str_repeat('The whānau business supplies seasonal produce with scheduled delivery and clear customer commitments. ', 8);
        $checks = $this->duplicates([
            ['heading' => true, 'text' => 'Delivery plan'],
            ['heading' => true, 'text' => " DELIVERY\n PLAN "],
            ['text' => '$100'],
            ['text' => '$100'],
            ['text' => $text],
            ['text' => str_replace('seasonal produce', 'seasonal products', $text)],
            ['text' => str_repeat('Equipment maintenance requires inspection records, spare parts and trained technicians. ', 8)],
        ]);

        self::assertSame([CheckStatus::Pass, CheckStatus::Fail, CheckStatus::Pass, CheckStatus::Pass,
            CheckStatus::Pass, CheckStatus::Fail, CheckStatus::Pass], array_column($checks, 'status'));
        $strict = $this->duplicates([['text' => $text], ['text' => $text.' An additional delivery.']], '100');
        self::assertSame(CheckStatus::Pass, $strict[1]->status);
        $exact = $this->duplicates([['text' => $text], ['text' => mb_strtoupper($text)]], '100');
        self::assertSame(CheckStatus::Fail, $exact[1]->status);
    }

    public function test_duplicate_review_compares_the_full_block_beyond_the_old_four_thousand_character_prefix(): void
    {
        $shared = str_repeat('A shared introduction describes the business and its purpose. ', 80);
        $checks = $this->duplicates([
            ['text' => $shared.str_repeat('Delivery scheduling and customer service require careful planning. ', 150)],
            ['text' => $shared.str_repeat('Equipment repairs and inventory storage depend on technical inspections. ', 150)],
        ]);

        self::assertSame(CheckStatus::Pass, $checks[1]->status);
    }

    /**
     * Processor seconds allowed for duplicate review of the oversized plan below.
     *
     * This is a regression guard on algorithmic cost, not a service-level
     * promise: fifty sections of sixty sentences is far longer than any real
     * plan, chosen so that anything quadratic in the comparison shows up by
     * orders of magnitude rather than by a few percent. The span measures
     * around 7 processor seconds on the development machine, so the budget is
     * set for roughly threefold headroom against slower hardware. A genuine
     * regression of the kind this was written for — comparing full block text
     * pairwise — does not land inside that margin.
     */
    private const DUPLICATE_REVIEW_BUDGET_SECONDS = 20.0;

    public function test_long_plan_duplicate_review_finishes_within_the_page_request_budget(): void
    {
        $blocks = [];
        for ($section = 0; $section < 50; $section++) {
            $sentences = [];
            for ($sentence = 0; $sentence < 60; $sentence++) {
                $identifier = hash('sha256', $section.':'.$sentence);
                $sentences[] = 'The business evidence '.$identifier.' supports this section of the operating plan.';
            }
            $blocks[] = ['text' => implode(' ', $sentences)];
        }
        $blocks[] = $blocks[0];
        $started = self::cpuSeconds();
        $checks = $this->duplicates($blocks);
        $spent = self::cpuSeconds() - $started;

        self::assertSame(CheckStatus::Fail, $checks[50]->status);
        self::assertSame(CheckStatus::Pass, $checks[49]->status);
        self::assertLessThan(self::DUPLICATE_REVIEW_BUDGET_SECONDS, $spent,
            'Duplicate review has become disproportionately expensive on a long plan.');
    }

    /**
     * Processor time this process has consumed, user and system together.
     *
     * The budget above used wall-clock, which on a machine running several
     * test processes at once measures the contention as well as the work: the
     * same check was observed at 4.4, 7.1, 10.0 and 13.0 seconds without the
     * rule changing, so the assertion failed on scheduling rather than on a
     * regression. Duplicate review is pure computation with no waiting in it,
     * so processor time is what the budget was always about.
     */
    private static function cpuSeconds(): float
    {
        $usage = getrusage();

        return ($usage['ru_utime.tv_sec'] ?? 0) + ($usage['ru_utime.tv_usec'] ?? 0) / 1_000_000
            + ($usage['ru_stime.tv_sec'] ?? 0) + ($usage['ru_stime.tv_usec'] ?? 0) / 1_000_000;
    }

    /** @param list<array{text:string,heading?:bool}> $rows
     * @return list<Subcheck>
     */
    private function duplicates(array $rows, string $threshold = '90'): array
    {
        $base = SyntheticCase::context([]);
        $source = SyntheticCase::fact('source', 'text')->sources[0];
        $blocks = [];
        foreach ($rows as $index => $row) {
            $blocks[] = new DocumentBlock((string) $index, $row['text'], $source, heading: $row['heading'] ?? false);
        }
        $context = new DocumentContext($base->source, $base->facts, 'plan', Audience::External, $blocks, true);

        return (new HygieneRule('DH-03'))->evaluate($context, new RuleConfiguration(['duplicate_similarity_percent' => $threshold]));
    }

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
