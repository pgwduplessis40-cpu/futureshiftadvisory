<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class FundingPositionRule implements Rule
{
    public function id(): string
    {
        return 'PM-12';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $checks = [];
        foreach ((new FundingSourcesRule)->evaluate($context, $configuration) as $check) {
            if ($check->status->unresolved()) {
                $checks[] = new Subcheck('source:'.$check->id, $check->status, Severity::Block, $check->message, $check->compared, $check->missingInputs);
            }
        }
        $position = $context->facts->one('plan.funding_position');
        $deficit = $context->facts->one('model.cash_deficit');
        $reserve = $context->facts->one('model.reserve_gap');
        if ($position === null || ! $position->confirmed || ! in_array($position->value, ['self_funded', 'external_funding'], true) || $deficit?->number() === null) {
            $checks[] = Subcheck::missing('cash-position', 'the confirmed funding position and cash deficit');
        } else {
            $checks[] = Checks::result('cash-position', $position->value !== 'self_funded' || ! $deficit->number()->isGreaterThan(0),
                'The '.$position->value.' claim has a forecast cash deficit of '.$deficit->value.'.', [$position, $deficit]);
            if ($position->value === 'external_funding') {
                $request = $context->facts->one('model.external_request');
                $purpose = $context->facts->one('funding.request_purpose');
                if ($request?->number() === null || ! FactValidation::date($request->asOf)
                    || ! $request->confirmed || ! is_string($purpose?->value) || trim($purpose->value) === '') {
                    $checks[] = Subcheck::missing('external-request', 'the separately dated proposed external request and its purpose');
                } else {
                    $checks[] = Checks::result('external-request', $request->number()->isPositive(),
                        'The external request is a separately stated positive amount with a dated purpose.', [$request, $purpose]);
                }
            }
        }
        $checks[] = $reserve?->number() === null
            ? Subcheck::missing('reserve', 'the separate reserve-target shortfall', Severity::Review)
            : Checks::result('reserve', ! $reserve->number()->isGreaterThan(0),
                'The reserve-target shortfall is '.$reserve->value.'. This is separate from a cash deficit or a borrowing request.', [$reserve], Severity::Review);

        return $checks;
    }
}
