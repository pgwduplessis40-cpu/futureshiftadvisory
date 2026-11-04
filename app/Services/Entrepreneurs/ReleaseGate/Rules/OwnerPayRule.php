<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class OwnerPayRule implements Rule
{
    public function id(): string
    {
        return 'PM-02';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $claims = $context->facts->all('plan.owner_pay');
        $checks = [Checks::currentClaims($claims)];
        foreach ($claims as $i => $claim) {
            $monthly = $this->monthly($claim);
            $model = $context->facts->one('model.owner_pay', $claim->entity, $claim->period, $claim->scenario);
            $modelMonthly = $model === null ? null : $this->monthly($model);
            $differentBasis = ($claim->basis !== null && $model?->basis !== null && $claim->basis !== $model->basis)
                || ($claim->asOf !== null && $model?->asOf !== null && $claim->asOf !== $model->asOf);
            if ($monthly === null || $modelMonthly === null || $differentBasis) {
                $checks[] = Subcheck::missing('model:'.$i, 'matching owner-pay rate, frequency and stage');
            } else {
                $checks[] = Checks::result('model:'.$i, Checks::close($monthly, $modelMonthly),
                    'Owner pay converts to '.$monthly.' per month in the plan and '.$modelMonthly.' in the model for '.$claim->period.'.', [$claim, $model]);
            }
            foreach (array_slice($claims, 0, $i) as $j => $other) {
                if ($other->entity !== $claim->entity || $other->period !== $claim->period || $other->scenario !== $claim->scenario
                    || ($other->basis !== null && $claim->basis !== null && $other->basis !== $claim->basis)) {
                    continue;
                }
                $otherMonthly = $this->monthly($other);
                if ($monthly !== null && $otherMonthly !== null) {
                    $checks[] = Checks::result('plan:'.$j.':'.$i, Checks::close($monthly, $otherMonthly),
                        'The same owner-pay stage converts to '.$monthly.' and '.$otherMonthly.' per month in the rendered plan.', [$other, $claim]);
                }
            }
            if ($claim->asOf === null || $claim->basis === null || $model?->asOf !== $claim->asOf || $model->basis !== $claim->basis) {
                $checks[] = Subcheck::missing('stage:'.$i, 'owner-pay effective dates, triggers and current-rate versus staged-average basis');
            }
        }

        return $checks;
    }

    private function monthly(Fact $fact): ?BigDecimal
    {
        $amount = $fact->number();
        if ($amount === null) {
            return null;
        }

        return match ($fact->unit) {
            'NZD/week' => $amount->multipliedBy(52)->dividedBy(12, 8, RoundingMode::HalfUp),
            'NZD/fortnight' => $amount->multipliedBy(26)->dividedBy(12, 8, RoundingMode::HalfUp),
            'NZD/year' => $amount->dividedBy(12, 8, RoundingMode::HalfUp),
            'NZD/month' => $amount,
            default => null,
        };
    }
}
