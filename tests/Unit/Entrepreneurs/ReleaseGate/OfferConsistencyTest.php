<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentBlock;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OfferConsistencyRule;
use App\Services\Entrepreneurs\ReleaseGate\SourceReference;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use PHPUnit\Framework\TestCase;

final class OfferConsistencyTest extends TestCase
{
    /**
     * @param  list<array{0:string,1?:string,2?:bool}>  $rows  text, section, quoted
     * @return list<Subcheck>
     */
    private function checks(array $rows, bool $complete = true): array
    {
        $base = SyntheticCase::context([]);
        $blocks = [];
        foreach ($rows as $index => [$text, $section, $quoted]) {
            $blocks[] = new DocumentBlock('block-'.$index, $text,
                new SourceReference('synthetic_excerpt', 'plan', $section, hash('sha256', 'offer-case-v1')),
                quotation: $quoted);
        }
        $context = new DocumentContext($base->source, $base->facts, 'plan', Audience::External, $blocks, $complete);

        return (new OfferConsistencyRule)->evaluate($context, new RuleConfiguration);
    }

    /** @param list<array{0:string,1?:string,2?:bool}> $rows */
    private function verdict(array $rows, bool $complete = true): ?CheckStatus
    {
        return (new RuleResult(RuleCatalogue::get('DH-07'), true, $this->checks($rows, $complete)))->status;
    }

    /**
     * The defect the rule exists for: a day rate in one section, the same days
     * bundled at a lower implied rate in another. The forecast section agrees
     * with the rate, so the finding names the two figures that do not and
     * leaves the third alone.
     */
    public function test_a_bundle_priced_below_the_stated_day_rate_is_reported_with_both_figures(): void
    {
        $checks = $this->checks([
            ['Studio Intensive delivery is priced at $1,500 + GST per day.', 'section:pricing', false],
            ['Evidence cites a four-day Studio Intensive at approximately $4,000.', 'section:evidence', false],
            ['Month 2 schedules four days of Studio Intensive at $6,000 + GST.', 'section:forecast-ramp', false],
        ]);

        self::assertCount(1, $checks, 'One offer and one unit is one comparison, not one per section.');
        self::assertSame('offer:studio-intensive:day', $checks[0]->id);
        self::assertSame(CheckStatus::Fail, $checks[0]->status);
        self::assertStringContainsString('$1500.00 per day GST exclusive (section:pricing)', $checks[0]->message);
        self::assertStringContainsString('$1000.00 per day on an unstated GST basis (section:evidence)', $checks[0]->message);
        // The figure that agrees is not accused, and all three readings are
        // shown so the advisor can see what was compared.
        self::assertStringNotContainsString('forecast-ramp', $checks[0]->message);
        self::assertCount(3, $checks[0]->compared);
        self::assertSame(['1500.00', '1000.00', '1500.00'], array_column($checks[0]->compared, 'value'));
    }

    /**
     * A GST-exclusive rate and a GST-inclusive bundle are one price stated two
     * ways. Reporting them as a difference would be reporting GST.
     */
    public function test_a_rate_and_a_bundle_on_different_gst_bases_reconcile_through_gst(): void
    {
        self::assertSame(CheckStatus::Pass, $this->verdict([
            ['Studio Intensive is $1,500 + GST per day.', 'section:pricing', false],
            ['Four days of Studio Intensive cost $6,900 including GST.', 'section:terms', false],
        ]));
        // The same pair where GST cannot explain the gap stays a finding.
        self::assertSame(CheckStatus::Fail, $this->verdict([
            ['Studio Intensive is $1,500 + GST per day.', 'section:pricing', false],
            ['Four days of Studio Intensive cost $5,000 including GST.', 'section:terms', false],
        ]));
    }

    /**
     * Two offers may legitimately charge different day rates. Grouping by the
     * unit alone would report that as an inconsistency, which is the false
     * finding this rule must not produce.
     */
    public function test_two_offers_priced_per_day_at_different_rates_are_not_one_inconsistency(): void
    {
        $checks = $this->checks([
            ['Studio Intensive is priced at $1,500 + GST per day.', 'section:pricing', false],
            ['Practice Clinic is priced at $800 + GST per day.', 'section:pricing', false],
        ]);

        self::assertSame(['offer:studio-intensive:day', 'offer:practice-clinic:day'], array_column($checks, 'id'));
        self::assertSame([CheckStatus::Pass, CheckStatus::Pass], array_column($checks, 'status'));
    }

    /**
     * A later shortened mention is the same offer. Treating it as a new one
     * would silently drop the comparison the rule exists to make.
     */
    public function test_a_shortened_mention_is_compared_against_the_fuller_name(): void
    {
        $checks = $this->checks([
            ['Creative Studio Intensive is priced at $1,500 + GST per day.', 'section:pricing', false],
            ['The Studio Intensive runs four days at $4,000 + GST.', 'section:delivery', false],
        ]);

        self::assertCount(1, $checks);
        self::assertSame(CheckStatus::Fail, $checks[0]->status);
    }

    /**
     * A price whose offer cannot be established is reported, not compared.
     * Guessing would hold two offers against each other; passing would say the
     * document was checked when nothing was.
     */
    public function test_a_price_with_no_named_offer_is_reported_rather_than_compared(): void
    {
        $checks = $this->checks([['Delivery is priced at $1,500 + GST per day.', 'section:pricing', false]]);

        self::assertCount(1, $checks);
        self::assertSame(CheckStatus::CannotVerify, $checks[0]->status);
        self::assertSame(['the offer the price at section:pricing belongs to'], $checks[0]->missingInputs);
    }

    /**
     * A monthly figure stated per month is one price. Read as a bundle as well
     * it would become a twelfth of itself and contradict its own sentence.
     */
    public function test_a_rate_quoted_against_its_own_term_is_read_once(): void
    {
        $checks = $this->checks([['Studio Retainer runs 12 months at $500 per month.', 'section:pricing', false]]);

        self::assertCount(1, $checks);
        self::assertSame(CheckStatus::Pass, $checks[0]->status);
        self::assertStringContainsString('$500.00 per month', $checks[0]->message);
        self::assertStringNotContainsString('41.6', $checks[0]->message);
    }

    /**
     * A rate displayed in whole dollars cannot state the bundle it implies
     * exactly. Rounding is not a pricing difference.
     */
    public function test_a_rounded_rate_agrees_with_the_bundle_it_implies(): void
    {
        self::assertSame(CheckStatus::Pass, $this->verdict([
            ['Studio Intensive is $1,667 per day.', 'section:pricing', false],
            ['Studio Intensive runs three days for $5,000.', 'section:delivery', false],
        ]));
    }

    /**
     * A quoted price is someone else's wording, as it is for DH-06. The
     * document is not inconsistent for reproducing it.
     */
    public function test_a_quoted_price_is_not_held_against_the_document(): void
    {
        self::assertSame(CheckStatus::Pass, $this->verdict([
            ['Studio Intensive is priced at $1,500 + GST per day.', 'section:pricing', false],
            ['The prior proposal offered four days of Studio Intensive at $4,000.', 'section:history', true],
        ]));
    }

    /**
     * No price to compare is out of scope with a stated reason, not a pass. A
     * pass here would read as a verified result nobody produced.
     */
    public function test_a_document_with_no_offer_price_is_out_of_scope_with_a_reason(): void
    {
        $checks = $this->checks([['The business delivers services to clients.', 'section:overview', false]]);

        self::assertCount(1, $checks);
        self::assertSame(CheckStatus::NotApplicable, $checks[0]->status);
        self::assertNotEmpty($checks[0]->scopeReason);
    }

    /** Incomplete rendered text cannot establish that the sections agree. */
    public function test_incomplete_rendered_text_cannot_confirm_consistency(): void
    {
        self::assertSame(CheckStatus::CannotVerify, $this->verdict(
            [['Studio Intensive is priced at $1,500 + GST per day.', 'section:pricing', false]], complete: false));
        self::assertSame(CheckStatus::CannotVerify, $this->verdict([]));
    }

    public function test_the_rule_reports_its_catalogue_identity(): void
    {
        self::assertSame('DH-07', (new OfferConsistencyRule)->id());
        self::assertSame('document_hygiene', RuleCatalogue::get('DH-07')->category);
    }
}
