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
            self::assertCount(11, $report->deferred());
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
        self::assertCount(26, $byRule);
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
