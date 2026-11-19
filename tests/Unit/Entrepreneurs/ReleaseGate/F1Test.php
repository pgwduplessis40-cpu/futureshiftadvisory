<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\PhaseOneRules;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use PHPUnit\Framework\TestCase;

final class F1Test extends TestCase
{
    public function test_all_enabled_rules_match_the_corrected_f1_package_expectations(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/ReleaseGate/f1-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $byRule = [];
        foreach (['plan', 'budget', 'brief'] as $kind) {
            $report = PhaseOneRules::evaluator()->evaluate(Fixture::f1($kind));
            self::assertCount(9, $report->deferred());
            self::assertFalse($report->checksResolved());
            self::assertFalse($report->jsonSerialize()['can_certify']);
            foreach ($report->results as $result) {
                if ($result->enabled) {
                    $byRule[$result->definition->id][] = $result;
                } else {
                    self::assertNull($result->status);
                }
            }
        }
        self::assertCount(28, $byRule);
        $mismatched = [];
        foreach ($byRule as $id => $results) {
            $states = array_map(fn (RuleResult $r): CheckStatus => $r->status, $results);
            // A rule that applies to none of the three documents is recorded
            // as out of scope rather than folded into a pass, because a pass
            // reads as a verified result nobody produced.
            $worst = in_array(CheckStatus::Fail, $states, true) ? CheckStatus::Fail
                : (in_array(CheckStatus::CannotVerify, $states, true) ? CheckStatus::CannotVerify
                    : (in_array(CheckStatus::Pass, $states, true) ? CheckStatus::Pass : CheckStatus::NotApplicable));
            // Collected rather than asserted one at a time, so a change that
            // moves several rules reports all of them instead of stopping at
            // whichever happens to come first in the catalogue.
            if ($manifest['expectations'][$id]['result'] !== $worst->value) {
                $mismatched[$id] = $manifest['expectations'][$id]['result'].' expected, '.$worst->value.' produced';
            }
        }
        self::assertSame([], $mismatched, 'Enabled rules disagreeing with the F1 package expectations.');
        $cash = $byRule['MI-01'][0];
        $balances = array_filter($cash->subchecks, fn ($s) => str_starts_with($s->id, 'balance:'));
        self::assertCount(12, $balances);
        foreach ($balances as $balance) {
            self::assertSame(CheckStatus::Pass, $balance->status);
        }
        foreach (['MI-03' => 'display-total', 'MI-04' => 'annual-display:0'] as $id => $checkId) {
            $subcheck = array_values(array_filter($byRule[$id][0]->subchecks, fn ($s) => $s->id === $checkId))[0];
            self::assertSame(CheckStatus::Pass, $subcheck->status);
        }
    }

    /**
     * PM-10 reports the two runways as different measures, not as a difference.
     *
     * The package originally specified Fail here. The plan claims about twelve
     * months from about $7,300 on essential operating costs of about $600 a
     * month, saying in terms that this excludes the owner's wages and assumes
     * no new revenue. The budget's own monthly cost basis is $4,335, of which
     * $3,792 is owner's compensation, so the model measures 1.74 months on a
     * basis that includes the owner pay the claim leaves out.
     *
     * Holding twelve against 1.74 would report a difference the two measures
     * are supposed to have, which is the mistake the rule exists to prevent,
     * so the expectation was revised against the source documents and the rule
     * names the model runway it would need instead. This test pins the reason:
     * a Cannot verify reached by rejecting the claim, or by finding no claim at
     * all, would satisfy the manifest while meaning something entirely
     * different.
     */
    public function test_the_runway_claim_and_the_model_are_reported_as_unlike_measures(): void
    {
        $report = PhaseOneRules::evaluator()->evaluate(Fixture::f1('plan'));
        $rule = array_values(array_filter($report->results, fn ($r) => $r->definition->id === 'PM-10'))[0];
        $byId = [];
        foreach ($rule->subchecks as $subcheck) {
            $byId[$subcheck->id] = $subcheck;
        }

        self::assertSame(CheckStatus::CannotVerify, $rule->status);
        self::assertSame(['no-sales:0', 'like-measure:0'], array_keys($byId),
            'The claim was read in full: anything less means it was rejected before the comparison.');
        self::assertSame(CheckStatus::Pass, $byId['no-sales:0']->status,
            'A no-sales claim that counts no receipts is consistent on its own basis.');
        self::assertSame(CheckStatus::CannotVerify, $byId['like-measure:0']->status);
        self::assertNotEmpty($byId['like-measure:0']->missingInputs);
        self::assertStringContainsString('owner pay', $byId['like-measure:0']->missingInputs[0],
            'The advisor is told which inclusion the model and the claim disagree on.');
    }

    public function test_internal_prompts_do_not_block_draft_viewing_and_are_not_reported_as_external_release(): void
    {
        $source = Fixture::f1('budget');
        $context = new DocumentContext($source->source, $source->facts, 'budget', Audience::Internal, $source->blocks, true);
        $report = PhaseOneRules::evaluator()->evaluate($context);
        $rule = array_values(array_filter($report->results, fn ($r) => $r->definition->id === 'DH-01'))[0];
        self::assertSame(CheckStatus::NotApplicable, $rule->status);
        self::assertNotEmpty($rule->subchecks[0]->scopeReason);
        self::assertSame('draft', $report->jsonSerialize()['status']);
    }
}
