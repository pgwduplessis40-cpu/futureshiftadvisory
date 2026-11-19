<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentBlock;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * DH-07: the same offer is described consistently across the sections.
 *
 * A price in a plan is a claim about an offer, and a reader comparing two
 * sections should find the same answer. The defect this exists for is a
 * document that prices a day of delivery in one section, bundles the same
 * days at a different implied rate in another, and leaves the reader to
 * notice.
 *
 * Two things decide whether a comparison is honest at all.
 *
 * The first is which offer a price belongs to. Two prices stated per day may
 * be one offer described twice or two offers priced differently, and the
 * second is not a defect. The rule therefore groups prices by the offer named
 * beside them and reports a price with no named offer as Cannot verify rather
 * than comparing it to anything. That direction is deliberate: a missed
 * comparison asks the document to name its offers, while a guessed one
 * reports a difference nobody claimed.
 *
 * The second is GST. A GST-exclusive rate and a GST-inclusive bundle are the
 * same price stated two ways, so figures are reconciled across every GST
 * reading their own wording leaves open, in the same spirit as PM-10 refusing
 * to hold two runways on different bases against each other. A difference
 * larger than any GST reading explains is a difference in the price.
 *
 * Scope: one document. The same metric across plan, brief and pack is NB-02's
 * comparison, not this one.
 *
 * @phpstan-type PricedOffer array{index:int,offer:string,words:list<string>,unit:string,rate:BigDecimal,gst:string,block:string,location:string,fact:Fact}
 */
final class OfferConsistencyRule implements Rule
{
    private const UNITS = 'day|hour|week|month|session|workshop|seat|place|participant';

    private const MONEY = '(?:NZ\$|NZD\s*|\$)\s*(\d[\d,]*(?:\.\d{1,2})?)';

    /** Goods and Services Tax Act 1985: the New Zealand rate is 15%. */
    private const GST = '1.15';

    private const EXCLUSIVE = 'GST exclusive';

    private const INCLUSIVE = 'GST inclusive';

    private const UNSTATED = 'GST basis unstated';

    /**
     * A rate displayed in whole dollars can differ from the rate a bundle
     * implies by half of its last displayed digit, which on four-figure prices
     * is under a twentieth of a percent. This band sits above that and well
     * below GST, so displayed rounding passes and a pricing difference does not.
     */
    private const TOLERANCE = '0.005';

    private const NUMBERS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
        'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12];

    /**
     * Words that carry no offer identity. A sentence beginning "The Studio
     * Intensive" capitalises its article, and counting it would stop that
     * mention joining "Creative Studio Intensive".
     */
    private const STOP = ['the', 'and', 'for', 'our', 'your', 'this', 'that', 'these', 'those', 'each', 'every'];

    public function id(): string
    {
        return 'DH-07';
    }

    /** @return list<Subcheck> */
    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $severity = RuleCatalogue::get($this->id())->severity;
        $statements = [];
        foreach ($context->blocks as $block) {
            // A quoted price is someone else's wording, as it is for DH-06.
            // The document is not inconsistent for reproducing it.
            if ($block->quotation) {
                continue;
            }
            foreach ($this->priced($block, count($statements)) as $statement) {
                $statements[] = $statement;
            }
        }
        $checks = [];
        if (! $context->renderedTextComplete || $context->blocks === []) {
            $checks[] = Subcheck::missing('rendered-content', 'complete rendered text from the selected renderer', $severity);
        }
        $named = [];
        foreach ($statements as $statement) {
            if ($statement['offer'] === '') {
                $checks[] = Subcheck::missing('unnamed:'.$statement['index'],
                    'the offer the price at '.$statement['location'].' belongs to', $severity, [$statement['fact']]);

                continue;
            }
            $named[] = $statement;
        }
        foreach ($this->groups($named) as $group) {
            $checks[] = $this->compare($group, $severity);
        }
        if ($statements === [] && $checks === []) {
            $checks[] = new Subcheck('offers', CheckStatus::NotApplicable, $severity,
                'This document states no offer price to compare.',
                scopeReason: 'The complete rendered text states no price against a named unit of delivery.');
        }

        return $checks;
    }

    /**
     * Every price in one block, as a rate per unit of delivery.
     *
     * @return list<PricedOffer>
     */
    private function priced(DocumentBlock $block, int $from): array
    {
        $found = [];
        foreach (preg_split('/(?<=[.!?;:])\s+|\R+/u', $block->text) ?: [] as $sentence) {
            $offer = $this->offer($sentence);
            $gst = $this->gst($sentence);
            foreach ($this->prices($sentence) as $price) {
                $found[] = ['index' => $from + count($found), 'offer' => $offer, 'words' => $this->words($offer),
                    'unit' => $price['unit'], 'rate' => $price['rate'], 'gst' => $gst, 'block' => $block->id,
                    'location' => $block->source->location,
                    'fact' => new Fact('document.blocks', Checks::money($price['rate']), entity: $block->id,
                        unit: 'NZD per '.$price['unit'], basis: $gst, sources: [$block->source])];
            }
        }

        return $found;
    }

    /**
     * A stated rate, or a quantity of units bundled into one amount.
     *
     * @return list<array{unit:string,rate:BigDecimal}>
     */
    private function prices(string $sentence): array
    {
        $prices = [];
        $consumed = [];
        preg_match_all('/'.self::MONEY.'(?:\s*(?:\+|plus)\s*GST)?\s*(?:per\s+|an?\s+|each\s+|\/\s*)('.self::UNITS.')\b/iu',
            $sentence, $rates, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($rates as $rate) {
            $consumed[$rate[1][1]] = true;
            $prices[] = ['unit' => mb_strtolower($rate[2][0]), 'rate' => $this->amount($rate[1][0])];
        }
        preg_match_all('/\b(\d{1,3}|'.implode('|', array_keys(self::NUMBERS)).')[-\s]+('.self::UNITS.')s?\b/iu',
            $sentence, $quantities, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($quantities as $quantity) {
            $after = $quantity[0][1] + strlen($quantity[0][0]);
            // A price a rate has already claimed is not also a bundle. Without
            // this, "12 months at $500 per month" would be read twice: once as
            // a $500 monthly rate and once as a $41.67 one.
            if (preg_match('/'.self::MONEY.'/u', $sentence, $amount, PREG_OFFSET_CAPTURE, $after) !== 1
                || isset($consumed[$amount[1][1]])) {
                continue;
            }
            $count = self::NUMBERS[mb_strtolower($quantity[1][0])] ?? (int) $quantity[1][0];
            if ($count < 1) {
                continue;
            }
            $consumed[$amount[1][1]] = true;
            $prices[] = ['unit' => mb_strtolower($quantity[2][0]),
                'rate' => $this->amount($amount[1][0])->dividedBy($count, 8, RoundingMode::HalfUp)];
        }

        return $prices;
    }

    private function amount(string $displayed): BigDecimal
    {
        return BigDecimal::of(str_replace(',', '', $displayed));
    }

    /**
     * The offer a sentence names, as the longest run of capitalised words in
     * it. A sentence that names none yields an empty string, which the rule
     * reports rather than resolving.
     *
     * Two substantive words are the minimum. One is not an identity: a
     * sentence opening "The Business charges" would otherwise name an offer
     * called Business, and the next section's unrelated price would join it.
     */
    private function offer(string $sentence): string
    {
        preg_match_all('/\b[A-Z][a-z]{2,}(?:\s+(?:of|the|and|for|&)\s+[A-Z][a-z]{2,}|\s+[A-Z][a-z]{2,}){1,4}\b/u',
            $sentence, $names);
        $best = '';
        $most = 1;
        foreach ($names[0] as $name) {
            $words = count($this->words($name));
            if ($words > $most) {
                $best = $name;
                $most = $words;
            }
        }

        return $best;
    }

    /** @return list<string> */
    private function words(string $name): array
    {
        preg_match_all('/\b[A-Z][a-z]{2,}/u', $name, $words);
        $lowered = array_diff(array_map(mb_strtolower(...), $words[0]), self::STOP);
        sort($lowered);

        return array_values(array_unique($lowered));
    }

    private function gst(string $sentence): string
    {
        $exclusive = preg_match('/(?:\+|plus)\s*GST|excl(?:uding|usive\s+of|\.)?\s*GST|GST[-\s]exclusive/i', $sentence) === 1;
        $inclusive = preg_match('/incl(?:uding|usive\s+of|\.)?\s*GST|GST[-\s]inclusive/i', $sentence) === 1;

        return match (true) {
            $exclusive && ! $inclusive => self::EXCLUSIVE,
            $inclusive && ! $exclusive => self::INCLUSIVE,
            default => self::UNSTATED,
        };
    }

    /**
     * Prices grouped by the offer and unit they describe.
     *
     * Fullest names first, so a later "the Studio Intensive" joins "Creative
     * Studio Intensive" instead of founding a group of its own. A name whose
     * words are not a subset stays separate: two offers priced per day are not
     * one offer described twice.
     *
     * @param  list<PricedOffer>  $statements
     * @return list<list<PricedOffer>>
     */
    private function groups(array $statements): array
    {
        usort($statements, fn (array $left, array $right): int => count($right['words']) <=> count($left['words'])
            ?: $left['index'] <=> $right['index']);
        $groups = [];
        foreach ($statements as $statement) {
            $joined = false;
            foreach ($groups as $key => $members) {
                if ($members[0]['unit'] === $statement['unit'] && array_diff($statement['words'], $members[0]['words']) === []) {
                    $groups[$key][] = $statement;
                    $joined = true;
                    break;
                }
            }
            if (! $joined) {
                $groups[] = [$statement];
            }
        }
        foreach ($groups as $key => $members) {
            usort($members, fn (array $left, array $right): int => $left['index'] <=> $right['index']);
            $groups[$key] = $members;
        }

        return $groups;
    }

    /**
     * Every pair, because agreement is a property of the whole set: a rate the
     * first statement reconciles with may still contradict the third. The work
     * is bounded by the number of priced phrases a rendered document contains.
     *
     * @param  list<PricedOffer>  $group
     */
    private function compare(array $group, Severity $severity): Subcheck
    {
        $id = 'offer:'.$this->slug($group[0]['offer']).':'.$group[0]['unit'];
        $facts = array_map(fn (array $statement): Fact => $statement['fact'], $group);
        foreach ($group as $left) {
            foreach ($group as $right) {
                if ($left['index'] >= $right['index'] || $this->reconciles($left, $right)) {
                    continue;
                }

                return new Subcheck($id, CheckStatus::Fail, $severity, $group[0]['offer'].' is priced at '
                    .$this->rate($left).' and at '.$this->rate($right).'.'.$this->note($left, $right), $facts);
            }
        }

        return new Subcheck($id, CheckStatus::Pass, $severity,
            $group[0]['offer'].' is priced at '.$this->rate($group[0]).' wherever this document states it.', $facts);
    }

    /** @param PricedOffer $statement */
    private function rate(array $statement): string
    {
        return '$'.Checks::money($statement['rate']).' per '.$statement['unit'].' '
            .($statement['gst'] === self::UNSTATED ? 'on an unstated GST basis' : $statement['gst'])
            .' ('.$statement['location'].')';
    }

    /**
     * @param  PricedOffer  $left
     * @param  PricedOffer  $right
     */
    private function note(array $left, array $right): string
    {
        if ($left['gst'] === self::UNSTATED || $right['gst'] === self::UNSTATED) {
            return ' No GST reading of the figure that states no basis reconciles the two.';
        }

        return $left['gst'] === $right['gst']
            ? ' Both figures are stated on the same GST basis.'
            : ' The figures do not reconcile across the GST bases they state.';
    }

    /**
     * @param  PricedOffer  $left
     * @param  PricedOffer  $right
     */
    private function reconciles(array $left, array $right): bool
    {
        $same = $left['gst'] === $right['gst'];
        $candidates = [];
        if ($same || $left['gst'] === self::UNSTATED || $right['gst'] === self::UNSTATED) {
            $candidates[] = $left['rate'];
        }
        // The left figure read as the GST-inclusive statement of the right,
        // where neither one's wording rules that reading out, and the reverse.
        if (! $same && $left['gst'] !== self::INCLUSIVE && $right['gst'] !== self::EXCLUSIVE) {
            $candidates[] = $left['rate']->multipliedBy(self::GST);
        }
        if (! $same && $left['gst'] !== self::EXCLUSIVE && $right['gst'] !== self::INCLUSIVE) {
            $candidates[] = $left['rate']->dividedBy(self::GST, 8, RoundingMode::HalfUp);
        }
        $relative = $right['rate']->abs()->multipliedBy(self::TOLERANCE);
        $tolerance = $relative->isGreaterThan('0.01') ? $relative : BigDecimal::of('0.01');
        foreach ($candidates as $candidate) {
            if ($candidate->minus($right['rate'])->abs()->isLessThanOrEqualTo($tolerance)) {
                return true;
            }
        }

        return false;
    }

    private function slug(string $offer): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($offer)) ?? $offer, '-');
    }
}
