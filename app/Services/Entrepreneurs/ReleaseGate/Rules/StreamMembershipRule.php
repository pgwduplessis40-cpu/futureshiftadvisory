<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;

final class StreamMembershipRule implements Rule
{
    public function id(): string
    {
        return 'PM-06';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $revenues = $context->facts->all('stream.revenue');
        if ($revenues === []) {
            return [Subcheck::missing('streams', 'per-stream revenue with an explicit base or upside flag')];
        }
        $checks = [];
        /** @var array<string,BigDecimal> $included */
        $included = [];
        /** @var array<string,true> $unresolved */
        $unresolved = [];
        foreach ($revenues as $i => $revenue) {
            $model = $context->facts->one('stream.membership', $revenue->entity, scenario: $revenue->scenario);
            $plan = $context->facts->one('plan.stream_membership', $revenue->entity, scenario: $revenue->scenario);
            $key = $revenue->scenario.':'.$revenue->period;
            // A missing flag is the defect this rule exists to find. Reading an
            // absent flag as excluded would quietly drop a stream out of the
            // base totals, and reporting the shortfall that follows as a
            // mismatch would state a difference nobody has established.
            if ($revenue->number() === null || ! is_bool($model?->value) || ! is_bool($plan?->value) || ! $model->confirmed || ! $plan->confirmed) {
                $checks[] = Subcheck::missing('membership:'.$i, 'a confirmed base/upside flag on both the plan and the model for '.$revenue->entity);
                $unresolved[$key] = true;

                continue;
            }
            $checks[] = Checks::result('agreement:'.$i, $model->value === $plan->value,
                $revenue->entity.' is '.($model->value ? 'included in' : 'excluded from').' the model base case and '
                    .($plan->value ? 'included in' : 'excluded from').' the current plan.', [$plan, $model]);
            if ($model->value) {
                // The total is built from the model's own flags. Whether the
                // plan agrees is the separate finding recorded just above.
                $included[$key] = ($included[$key] ?? BigDecimal::of(0))->plus($revenue->number());
            }
        }
        $totals = $context->facts->all('model.base_revenue');
        if ($totals === []) {
            $checks[] = Subcheck::missing('base-total', 'the monthly base revenue total the included streams must equal');

            return $checks;
        }
        foreach ($totals as $j => $total) {
            $key = $total->scenario.':'.$total->period;
            $checks[] = $total->number() === null || isset($unresolved[$key]) || ! isset($included[$key])
                ? Subcheck::missing('base-total:'.$j, 'a flag on every stream contributing to '.$total->period.' before its base total can be checked')
                : Checks::result('base-total:'.$j, Checks::close($included[$key], $total->number()),
                    'The '.$total->period.' base total of '.$total->value.' equals only the streams flagged as included.', [$total]);
        }

        return $checks;
    }
}
