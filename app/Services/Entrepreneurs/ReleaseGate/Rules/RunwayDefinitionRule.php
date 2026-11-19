<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RunwayFacts;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class RunwayDefinitionRule implements Rule
{
    /**
     * The model publishes one runway per basis, under that basis as its entity.
     *
     * Named by the producer rather than repeated here. A basis renamed on one
     * side of a literal list and not the other would not fail: the rule would
     * quietly stop recognising a claim it used to read.
     */
    private const BASES = [RunwayFacts::FORECAST_CASH, RunwayFacts::NO_SALES];

    public function id(): string
    {
        return 'PM-10';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $claims = $context->facts->all('runway.months');
        if ($claims === []) {
            return [Subcheck::missing('runway', 'each runway claim with its start cash, period, cost basis and inclusions', Severity::Review)];
        }
        $checks = [];
        foreach ($claims as $i => $claim) {
            $start = $context->facts->one('runway.start_cash', $claim->entity, $claim->period, $claim->scenario);
            $receipts = $context->facts->one('runway.includes_receipts', $claim->entity, $claim->period, $claim->scenario);
            $ownerPay = $context->facts->one('runway.includes_owner_pay', $claim->entity, $claim->period, $claim->scenario);
            $basis = $claim->basis;
            if ($claim->number() === null || $claim->unit !== 'months' || $start?->number() === null
                || ! in_array($basis, self::BASES, true) || ! is_bool($receipts?->value) || ! is_bool($ownerPay?->value)) {
                $checks[] = Subcheck::missing('definition:'.$i, 'start cash, a named cost basis and receipt and owner-pay inclusions for the '.$claim->entity.' runway claim', Severity::Review);

                continue;
            }
            if ($basis === 'essential_cost_no_sales') {
                $checks[] = Checks::result('no-sales:'.$i, $receipts->value === false,
                    'An essential-cost runway stated on a no-sales basis cannot also rely on future customer receipts.', [$receipts], Severity::Review);
            }
            $model = $context->facts->one('model.runway_months', $basis, scenario: $claim->scenario);
            $modelReceipts = $context->facts->one('runway.includes_receipts', $basis, scenario: $claim->scenario);
            $modelOwnerPay = $context->facts->one('runway.includes_owner_pay', $basis, scenario: $claim->scenario);
            if ($model?->number() === null || ! is_bool($modelReceipts?->value) || ! is_bool($modelOwnerPay?->value)) {
                $checks[] = Subcheck::missing('model:'.$i, 'the model runway on the '.$basis.' basis with its own stated inclusions', Severity::Review);

                continue;
            }
            // Comparing a claim that counts future receipts with a model figure
            // that does not would report a difference the two measures are
            // supposed to have. They are never reconciled against each other.
            //
            // What the document owes its reader instead is both figures. A
            // runway stated on one basis alone is true and reads as the whole
            // answer: an owner-excluded twelve months and an owner-included
            // 1.74 describe the same bank balance, and only one of them is on
            // the page. So the rule looks for a second claim stating the
            // measure this one leaves out, and reports the omission where
            // there is none. It does not decide which figure is the right
            // one; that is the reader's call, and they can only make it with
            // both in front of them.
            if ($modelReceipts->value !== $receipts->value || $modelOwnerPay->value !== $ownerPay->value) {
                $checks[] = Checks::result('both-bases:'.$i, $this->alsoClaimed($context, $claim, $modelReceipts, $modelOwnerPay),
                    $claim->entity.' states '.$claim->value.' months '.$this->inclusions($receipts, $ownerPay)
                        .'. On the same opening cash the model measures '.$model->value.' months '
                        .$this->inclusions($modelReceipts, $modelOwnerPay)
                        .'. Both readings are true and neither replaces the other, so both belong in the document.',
                    [$claim, $model, $receipts, $ownerPay, $modelReceipts, $modelOwnerPay], Severity::Review, '0.5');

                continue;
            }
            $checks[] = Checks::result('runway:'.$i, Checks::close($claim->number(), $model->number(), '0.5'),
                $claim->entity.' claims '.$claim->value.' months from '.$start->value.' on the '.$basis.' basis against '.$model->value.' in the model.',
                [$start, $claim, $model], Severity::Review, '0.5');
        }

        return $checks;
    }

    /**
     * Whether the document also states the measure this claim leaves out.
     *
     * A plan that gives both runways confirms two claims on the same basis,
     * each carrying its own inclusions, so the partner is found by matching
     * the model's answers rather than by counting claims. Without this a plan
     * that discloses both would still be reported for the first of them.
     */
    private function alsoClaimed(DocumentContext $context, Fact $claim, Fact $modelReceipts, Fact $modelOwnerPay): bool
    {
        foreach ($context->facts->all('runway.months') as $other) {
            if ($other === $claim || $other->basis !== $claim->basis || $other->scenario !== $claim->scenario) {
                continue;
            }
            $receipts = $context->facts->one('runway.includes_receipts', $other->entity, $other->period, $other->scenario);
            $ownerPay = $context->facts->one('runway.includes_owner_pay', $other->entity, $other->period, $other->scenario);
            if ($receipts?->value === $modelReceipts->value && $ownerPay?->value === $modelOwnerPay->value) {
                return true;
            }
        }

        return false;
    }

    /** Stated as what the figure counts, because "false" is an answer and not a gap. */
    private function inclusions(Fact $receipts, Fact $ownerPay): string
    {
        return ($ownerPay->value === true ? 'including owner pay' : 'excluding owner pay')
            .($receipts->value === true ? ' and counting future receipts' : ' and assuming no new revenue');
    }
}
