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
    public function test_all_twenty_enabled_rules_match_the_corrected_f1_package_expectations(): void
    {
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/ReleaseGate/f1-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $byRule = [];
        foreach (['plan', 'budget', 'brief'] as $kind) {
            $report = PhaseOneRules::evaluator()->evaluate(Fixture::f1($kind));
            self::assertCount(17, $report->deferred());
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
        self::assertCount(20, $byRule);
        foreach ($byRule as $id => $results) {
            $states = array_map(fn (RuleResult $r): CheckStatus => $r->status, $results);
            $worst = in_array(CheckStatus::Fail, $states, true) ? CheckStatus::Fail
                : (in_array(CheckStatus::CannotVerify, $states, true) ? CheckStatus::CannotVerify : CheckStatus::Pass);
            self::assertSame($manifest['expectations'][$id]['result'], $worst->value, $id);
        }
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
