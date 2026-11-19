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
use App\Services\Entrepreneurs\ReleaseGate\Rules\OfferConsistencyRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OpeningCashRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OwnerPayRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ProfitabilityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\RevenueRampRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\RunwayDefinitionRule;
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
            // Eight of the nine phase-2 rules. Each reports Cannot verify
            // where its evidence is absent, which is both the documented
            // expectation and the honest answer.
            //
            // PM-08 stays deferred. The package expects it to Fail on this
            // plan, and a rule with no facts can only report its inputs
            // missing; registering it now would record Cannot verify where a
            // contradiction is specified, which understates the finding
            // rather than making it.
            //
            // PM-10 and PM-05 are here on the opposite footing: for each, the
            // package's expectation was revised against the source documents
            // rather than against this implementation's output. PM-10's plan
            // runway excludes the owner's wages while the model's cost basis
            // includes them, so it reports the model runway on the claim's own
            // basis as missing instead of holding two measures against each
            // other. PM-05's plan states revenue as date-bounded milestone
            // ranges and a longer-term capacity aim, never as a schedule by
            // stream and month, so there is no ramp claim to contradict.
            new ElapsedActualsRule, new StreamMembershipRule, new SharedCapacityRule, new MonthThirteenBridgeRule,
            new StressDimensionRule, new ScenarioFundingRule, new RunwayDefinitionRule, new RevenueRampRule,
            ...array_map(fn (int $i): Rule => new HygieneRule(sprintf('DH-%02d', $i)), range(1, 6)),
            // DH-07 completes the document-hygiene set. The catalogue carries
            // it as phase 3 alongside NB-01 and NB-02, but those two wait on
            // typed narrative binding while this one reads document.blocks,
            // which every other hygiene rule already reads in full. Nothing it
            // needs is outstanding, so it is registered rather than deferred
            // behind work it does not depend on.
            new OfferConsistencyRule];
    }

    public static function evaluator(): EvaluateReleaseGate
    {
        return new EvaluateReleaseGate(self::all());
    }
}
