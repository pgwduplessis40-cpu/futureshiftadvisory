<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class TaxRule implements Rule
{
    public function id(): string
    {
        return 'MI-04';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $checks = [];
        foreach ($context->facts->all('tax.profit_display', 'base') as $i => $profit) {
            $tax = $context->facts->one('tax.expense_display', $profit->entity, $profit->period);
            $rate = $context->facts->one('tax.rate_display', $profit->entity, $profit->period);
            if ($profit->number() !== null && $tax?->number() !== null && $rate?->number() !== null) {
                $expected = $profit->number()->multipliedBy($rate->number())->dividedBy(100, 8, RoundingMode::HalfUp);
                $matches = Checks::money($expected, 0) === Checks::money($tax->number(), 0);
                $checks[] = $matches ? Checks::result('annual-display:'.$i, true,
                    'Annual tax agrees at published whole-dollar precision; this is not the full loss or payment check.', [$profit, $tax, $rate], precision: '1')
                    : Subcheck::missing('annual-display:'.$i, 'the loss offsets and adjustments explaining the annual displayed tax');
            }
        }
        $policy = $context->facts->one('tax.policy');
        $openingLosses = $context->facts->one('tax.opening_losses');
        $rate = $context->facts->one('tax.rate');
        $profits = $context->facts->all('tax.profit', 'base');
        if ($policy?->value !== 'loss_carry_forward' || ! $policy->confirmed || $openingLosses?->number() === null || $rate?->number() === null || $profits === []
            || $openingLosses->number()->isNegative() || $rate->number()->isNegative() || $rate->number()->isGreaterThan(100)) {
            return [...$checks, Subcheck::missing('tax-policy', 'entity/tax policy, eligible losses, adjustments and a separate cash-tax due-date schedule')];
        }
        usort($profits, fn (Fact $a, Fact $b): int => strnatcmp($a->period, $b->period));
        $losses = $openingLosses->number();
        if (! FactValidation::monthlyCoverage($profits, FactValidation::horizon($context->facts->one('model.horizon')))) {
            $checks[] = Subcheck::missing('coverage', 'a complete monthly tax schedule for the required horizon');
        }
        $chainComplete = true;
        foreach ($profits as $i => $profit) {
            $adjustment = $context->facts->one('tax.adjustment', $profit->entity, $profit->period);
            $expense = $context->facts->one('tax.expense', $profit->entity, $profit->period);
            $closingLosses = $context->facts->one('tax.closing_losses', $profit->entity, $profit->period);
            if (! $chainComplete || $profit->period !== 'month:'.($i + 1) || $profit->number() === null || $adjustment?->number() === null || $expense?->number() === null || $closingLosses?->number() === null) {
                $checks[] = Subcheck::missing('tax:'.$i, 'profit, eligible adjustments, tax expense and carried losses for '.$profit->period);
                $chainComplete = false;

                continue;
            }
            $taxable = $profit->number()->plus($adjustment->number());
            $used = $taxable->isGreaterThan(0) ? ($taxable->isLessThan($losses) ? $taxable : $losses) : BigDecimal::of(0);
            $losses = $taxable->isNegative() ? $losses->minus($taxable) : $losses->minus($used);
            $expected = $taxable->isGreaterThan($used) ? $taxable->minus($used)->multipliedBy($rate->number())->dividedBy(100, 8, RoundingMode::HalfUp) : BigDecimal::of(0);
            $checks[] = Checks::result('expense:'.$i, Checks::close($expected, $expense->number()) && Checks::close($losses, $closingLosses->number()),
                'Tax expense and eligible carried losses reconcile without an automatic loss refund.', [$profit, $adjustment, $expense, $closingLosses]);
            $payment = $context->facts->one('tax.payment', $profit->entity, $profit->period);
            $payable = $context->facts->one('tax.payable_movement', $profit->entity, $profit->period);
            if ($payment?->number() === null || $payable?->number() === null || ! FactValidation::date($payment->asOf) || $payment->sources === []) {
                $checks[] = Subcheck::missing('cash-tax:'.$i, 'a separately dated tax payment and tax-payable reconciliation');
            } else {
                $checks[] = Checks::result('cash-tax:'.$i, Checks::close($payment->number()->plus($payable->number()), $expense->number()),
                    'Tax payment timing and the tax-payable movement reconcile to the expense.', [$payment, $payable, $expense]);
                $coverage = $context->facts->one('ledger.coverage', period: $profit->period);
                $taxLines = [];
                $complete = true;
                foreach ($context->facts->all('ledger.category', 'base') as $category) {
                    if ($category->period === $profit->period && $category->value === 'cash_tax') {
                        $line = $context->facts->one('ledger.amount', $category->entity, $category->period);
                        if ($line !== null) {
                            $taxLines[] = $line;
                        } else {
                            $complete = false;
                        }
                    }
                }
                $sum = $taxLines === [] ? BigDecimal::of(0) : Checks::sum($taxLines);
                $checks[] = $coverage?->value !== 'complete' || ! $coverage->confirmed || ! $complete || $sum === null
                    ? Subcheck::missing('cash-ledger:'.$i, 'the complete cash-tax ledger for '.$profit->period)
                    : Checks::result('cash-ledger:'.$i, Checks::close($sum, $payment->number()), 'Scheduled tax cash equals the cash-tax ledger for this month.', [$payment, ...$taxLines]);
            }
        }

        return $checks;
    }

    /** @return list<Subcheck> */
    public function scenario(DocumentContext $context, RuleConfiguration $configuration, string $scenario): array
    {
        $facts = array_values(array_filter($context->facts->all(), fn (Fact $f): bool => $f->scenario === $scenario || $f->key === 'model.horizon'));
        $facts = array_map(fn (Fact $f): Fact => new Fact($f->key, $f->value, $f->entity, $f->period, 'base', $f->unit, $f->basis, $f->asOf, $f->confirmed, $f->sources), $facts);
        $scoped = new DocumentContext($context->source, new FactSet($facts), $context->document, $context->audience);

        return $this->evaluate($scoped, $configuration);
    }
}
