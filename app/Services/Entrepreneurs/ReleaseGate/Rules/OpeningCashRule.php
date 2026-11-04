<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\RoundingMode;

final class OpeningCashRule implements Rule
{
    public function id(): string
    {
        return 'PM-03';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $claims = $context->facts->all('plan.opening_cash');
        $checks = [Checks::currentClaims($claims)];
        foreach ($claims as $i => $claim) {
            $model = $context->facts->one('model.opening_cash', $claim->entity, $claim->period, $claim->scenario);
            if ($claim->number() === null || $model?->number() === null || ! FactValidation::date($claim->asOf) || ! FactValidation::date($model->asOf)
                || $claim->basis === null || $model->basis !== $claim->basis || $claim->unit === null || $model->unit !== $claim->unit) {
                $checks[] = Subcheck::missing('basis:'.$i, 'cash date, currency, account/restriction scope and comparable balances', compared: [$claim, ...($model === null ? [] : [$model])]);

                continue;
            }
            $left = $claim->number();
            $facts = [$claim, $model];
            if ($claim->asOf !== $model->asOf) {
                $movements = array_values(array_filter($context->facts->all('cash.bridge.movement'),
                    fn (Fact $f): bool => $f->entity === $claim->entity && $f->period === $claim->asOf.'/'.$model->asOf
                        && $f->unit === $claim->unit && $f->confirmed && $f->sources !== []));
                $bridge = Checks::sum($movements);
                $coverage = $context->facts->one('cash.bridge.coverage', $claim->entity, $claim->asOf.'/'.$model->asOf);
                if ($movements === [] || $bridge === null || $coverage?->value !== 'complete' || ! $coverage->confirmed) {
                    $checks[] = Subcheck::missing('bridge:'.$i, 'a complete sourced cash roll-forward between '.$claim->asOf.' and '.$model->asOf);

                    continue;
                }
                $left = $left->plus($bridge);
                $facts = [...$facts, ...$movements, $coverage];
            }
            $right = $model->number();
            $basis = $left->abs()->isGreaterThan($right->abs()) ? $left->abs() : $right->abs();
            $tolerance = $basis->multipliedBy($configuration->threshold('opening_cash_tolerance_percent'))->dividedBy(100, 8, RoundingMode::HalfUp);
            $checks[] = Checks::result('balance:'.$i, $left->minus($right)->abs()->isLessThanOrEqualTo($tolerance),
                'Comparable opening cash is '.$left.' in the plan and '.$right.' in the model.', $facts, precision: (string) $tolerance);
        }

        return $checks;
    }
}
