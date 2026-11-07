<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class RunwayDefinitionRule implements Rule
{
    /** The model publishes one runway per basis, under that basis as its entity. */
    private const BASES = ['forecast_cash', 'essential_cost_no_sales'];

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
            // supposed to have. They are reconciled separately or not at all.
            if ($modelReceipts->value !== $receipts->value || $modelOwnerPay->value !== $ownerPay->value) {
                $checks[] = Subcheck::missing('like-measure:'.$i, 'a model runway including the same future receipts and owner pay as the '.$claim->entity.' claim', Severity::Review);

                continue;
            }
            $checks[] = Checks::result('runway:'.$i, Checks::close($claim->number(), $model->number(), '0.5'),
                $claim->entity.' claims '.$claim->value.' months from '.$start->value.' on the '.$basis.' basis against '.$model->value.' in the model.',
                [$start, $claim, $model], Severity::Review, '0.5');
        }

        return $checks;
    }
}
