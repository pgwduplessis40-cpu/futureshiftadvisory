<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Rules\AssetClassificationRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CapacityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CashLedgerRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CommittedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ElapsedActualsRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FixedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingPositionRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingSourcesRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HorizonRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HygieneRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\MarginRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\MonthThirteenBridgeRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OpeningCashRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OwnerPayRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ProfitabilityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioFundingRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\SharedCapacityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StreamMembershipRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StressDimensionRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\TaxRule;

final class PhaseOneRules
{
    /** @return list<Rule> */
    public static function all(): array
    {
        return [new ProfitabilityRule, new OwnerPayRule, new OpeningCashRule, new CapacityRule, new MarginRule,
            new CommittedCostRule, new FundingPositionRule, new CashLedgerRule, new FundingSourcesRule,
            new FixedCostRule, new TaxRule, new AssetClassificationRule, new HorizonRule, new ScenarioRule,
            // Six of the nine phase-2 rules, being the six that reach the
            // verdict the F1 reference package specifies for them. Each
            // reports Cannot verify where its evidence is absent, which is
            // the documented expectation and the honest answer.
            //
            // PM-05, PM-08 and PM-10 stay deferred. The package expects each
            // to Fail on this plan, and a rule with no facts can only report
            // its inputs missing; registering one now would record Cannot
            // verify where a contradiction is specified, which understates
            // the finding rather than making it.
            new ElapsedActualsRule, new StreamMembershipRule, new SharedCapacityRule, new MonthThirteenBridgeRule,
            new StressDimensionRule, new ScenarioFundingRule,
            ...array_map(fn (int $i): Rule => new HygieneRule(sprintf('DH-%02d', $i)), range(1, 6))];
    }

    public static function evaluator(): EvaluateReleaseGate
    {
        return new EvaluateReleaseGate(self::all());
    }
}
