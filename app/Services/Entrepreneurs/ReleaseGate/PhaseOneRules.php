<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Rules\AssetClassificationRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CapacityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CashLedgerRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CommittedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FixedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingPositionRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingSourcesRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HorizonRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HygieneRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\MarginRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OpeningCashRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OwnerPayRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ProfitabilityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\TaxRule;

final class PhaseOneRules
{
    /** @return list<Rule> */
    public static function all(): array
    {
        return [new ProfitabilityRule, new OwnerPayRule, new OpeningCashRule, new CapacityRule, new MarginRule,
            new CommittedCostRule, new FundingPositionRule, new CashLedgerRule, new FundingSourcesRule,
            new FixedCostRule, new TaxRule, new AssetClassificationRule, new HorizonRule, new ScenarioRule,
            ...array_map(fn (int $i): Rule => new HygieneRule(sprintf('DH-%02d', $i)), range(1, 6))];
    }

    public static function evaluator(): EvaluateReleaseGate
    {
        return new EvaluateReleaseGate(self::all());
    }
}
