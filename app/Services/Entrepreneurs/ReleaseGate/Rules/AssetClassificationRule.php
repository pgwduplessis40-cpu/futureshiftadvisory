<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class AssetClassificationRule implements Rule
{
    public function id(): string
    {
        return 'MI-05';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $amounts = $context->facts->all('cost.amount');
        if ($amounts === []) {
            return [Subcheck::missing('inventory', 'asset-like expenditure and the accounting classification policy', Severity::Review)];
        }
        $checks = [];
        foreach ($amounts as $i => $amount) {
            $asset = $context->facts->one('cost.asset_like', $amount->entity, $amount->period);
            if ($amount->number() === null || ! is_bool($asset?->value)) {
                $checks[] = Subcheck::missing('asset:'.$i, 'amount and asset-like classification for '.$amount->entity, Severity::Review);

                continue;
            }
            if (! $asset->value || $amount->number()->isLessThan($configuration->threshold('asset_review'))) {
                $checks[] = new Subcheck('asset:'.$i, CheckStatus::NotApplicable, Severity::Review,
                    'This item is outside the asset-classification review trigger.', [$amount, $asset], scopeReason: 'Explicit non-asset classification or amount below the configured review threshold.');

                continue;
            }
            $classification = $context->facts->one('cost.classification', $amount->entity, $amount->period);
            $policy = $context->facts->one('cost.policy', $amount->entity, $amount->period);
            $reviewed = $policy?->confirmed && is_string($policy->value) && mb_strlen($policy->value) >= 15;
            if ($classification === null || ! in_array($classification->value, ['operating', 'capital'], true)) {
                $checks[] = Subcheck::missing('classification:'.$i, 'the accounting classification for '.$amount->entity, Severity::Review);
            } else {
                $checks[] = Checks::result('classification:'.$i, $classification->value !== 'operating' || $reviewed,
                    'Review '.$amount->entity.' at '.$amount->value.' in '.$amount->period.' using its policy, useful life and cash date; amount alone does not determine capex.',
                    [$amount, $asset, $classification, ...($policy === null ? [] : [$policy])], Severity::Review);
            }
        }

        return $checks;
    }
}
