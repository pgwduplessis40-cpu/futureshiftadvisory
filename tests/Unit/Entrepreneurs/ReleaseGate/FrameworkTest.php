<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\EvaluateReleaseGate;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\GateReport;
use App\Services\Entrepreneurs\ReleaseGate\RiskAcceptance;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use PHPUnit\Framework\TestCase;

final class FrameworkTest extends TestCase
{
    public function test_catalogue_declares_all_rules_and_phase_one_scope(): void
    {
        $catalogue = RuleCatalogue::all();
        self::assertCount(37, $catalogue);
        self::assertCount(37, array_unique(array_column($catalogue, 'id')));
        self::assertCount(20, array_filter($catalogue, fn ($r) => $r->phase === 1));
        foreach ($catalogue as $rule) {
            self::assertNotEmpty($rule->inputs);
            self::assertNotEmpty($rule->condition);
            if ($rule->severity === Severity::Block) {
                self::assertFalse($rule->acceptsElevatedRisk());
            }
        }
    }

    public function test_missing_subcheck_prevents_pass_without_erasing_positive_assertions(): void
    {
        $rule = RuleCatalogue::get('MI-01');
        $pass = new Subcheck('balance', CheckStatus::Pass, Severity::Block, 'The balance reconciles.');
        $unknown = Subcheck::missing('ledger', 'the full dated ledger');
        $result = new RuleResult($rule, true, [$pass, $unknown]);
        self::assertSame(CheckStatus::CannotVerify, $result->status);
        self::assertSame(CheckStatus::Pass, $result->subchecks[0]->status);
        self::assertSame(CheckStatus::CannotVerify, (new RuleResult($rule, true))->status);
        self::assertNull((new RuleResult($rule, false))->status);
        self::assertSame(CheckStatus::Fail, (new RuleResult($rule, true, [$unknown,
            new Subcheck('mismatch', CheckStatus::Fail, Severity::Block, 'Different balances.')]))->status);
    }

    public function test_not_applicable_needs_a_scope_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Subcheck('no-data', CheckStatus::NotApplicable, Severity::Block, 'No data.');
    }

    public function test_missing_inputs_cannot_be_labelled_pass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Subcheck('missing', CheckStatus::Pass, Severity::Block, 'Fine.', missingInputs: ['cash']);
    }

    public function test_empty_registry_and_all_passing_enabled_checks_cannot_certify(): void
    {
        $context = $this->context();
        $empty = (new EvaluateReleaseGate)->evaluate($context);
        self::assertCount(37, $empty->deferred());
        self::assertFalse($empty->jsonSerialize()['can_certify']);
        $rule = new class implements Rule
        {
            public function id(): string
            {
                return 'MI-01';
            }

            public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
            {
                return [new Subcheck('balance', CheckStatus::Pass, Severity::Block, 'Balanced.')];
            }
        };
        $report = (new EvaluateReleaseGate([$rule]))->evaluate($context);
        self::assertCount(36, $report->deferred());
        self::assertTrue($report->checksResolved());
        self::assertFalse($report->jsonSerialize()['can_certify']);
    }

    public function test_integrity_outcomes_cannot_be_accepted_or_downgraded(): void
    {
        $context = $this->context();
        $check = Subcheck::missing('ledger', 'dated cash movements');
        $result = new RuleResult(RuleCatalogue::get('MI-01'), true, [$check]);
        $config = new RuleConfiguration;
        $report = new GateReport($context, $config, [$result]);
        $decision = $this->decision($report, $result, $check);
        self::assertFalse((new GateReport($context, $config, [$result], [$decision]))->checksResolved());
        $this->expectException(\InvalidArgumentException::class);
        new RuleConfiguration(severities: ['MI-01' => 'review']);
    }

    public function test_risk_acceptance_retains_result_and_expires_when_dependencies_or_policy_change(): void
    {
        $check = new Subcheck('target', CheckStatus::Fail, Severity::Review, 'Explain the target period.');
        $result = new RuleResult(RuleCatalogue::get('PM-07'), true, [$check]);
        $config = new RuleConfiguration(severities: ['PM-07' => 'block']);
        $report = new GateReport($this->context(), $config, [$result]);
        $decision = $this->decision($report, $result, $check);
        $accepted = new GateReport($report->context, $config, [$result], [$decision]);
        self::assertTrue($accepted->checksResolved());
        self::assertSame(CheckStatus::Fail, $accepted->results[0]->status);
        self::assertTrue((new GateReport($this->context(content: 'new summary'), $config, [$result], [$decision]))->checksResolved());
        self::assertFalse((new GateReport($this->context(inputs: 'changed cash'), $config, [$result], [$decision]))->checksResolved());
        self::assertFalse((new GateReport($report->context, new RuleConfiguration, [$result], [$decision]))->checksResolved());
    }

    public function test_reserve_review_cannot_accept_associated_source_block(): void
    {
        $review = new Subcheck('reserve', CheckStatus::Fail, Severity::Review, 'Review the reserve gap.');
        $missing = Subcheck::missing('funding', 'source identity');
        $result = new RuleResult(RuleCatalogue::get('PM-12'), true, [$review, $missing]);
        $report = new GateReport($this->context(), new RuleConfiguration, [$result]);
        $accepted = new GateReport($report->context, $report->configuration, [$result], [$this->decision($report, $result, $review)]);
        self::assertFalse($accepted->checksResolved());
        self::assertTrue($accepted->findings()[0]['accepted']);
        self::assertFalse($accepted->findings()[1]['accepted']);
    }

    public function test_fact_order_is_stable_and_competing_values_are_not_silently_selected(): void
    {
        $a = new Fact('cash', '0');
        $b = new Fact('cash', null);
        self::assertNotNull($a->number());
        self::assertNull($b->number());
        self::assertSame(CanonicalJson::hash(new FactSet([$a, $b])), CanonicalJson::hash(new FactSet([$b, $a])));
        self::assertNull((new FactSet([$a, $b]))->one('cash'));
        self::assertSame(CanonicalJson::hash(['b' => 2, 'a' => 1]), CanonicalJson::hash(['a' => 1, 'b' => 2]));
    }

    public function test_f1_manifest_hash_and_observable_cash_arithmetic_are_independent(): void
    {
        $path = dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/ReleaseGate/';
        $manifest = json_decode(file_get_contents($path.'f1-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($manifest['derivative']['sha256'], hash_file('sha256', $path.'f1.json'));
        self::assertCount(37, $manifest['expectations']);
        $fixture = json_decode(file_get_contents($path.'f1.json'), true, flags: JSON_THROW_ON_ERROR);
        $rows = [];
        foreach ($fixture['facts'] as $fact) {
            if (in_array($fact['key'], ['cash.opening', 'cash.movement', 'cash.closing'], true)) {
                $rows[$fact['period']][$fact['key']] = (int) round((float) $fact['value'] * 100);
            }
        }
        self::assertCount(12, $rows);
        $movements = 0;
        foreach ($rows as $row) {
            self::assertSame($row['cash.closing'], $row['cash.opening'] + $row['cash.movement']);
            $movements += $row['cash.movement'];
        }
        self::assertSame(6002056, $movements);
        self::assertSame(6755156, $rows['month:12']['cash.closing']);
        self::assertNotEmpty($fixture['unknown_inputs']);
    }

    private function context(string $inputs = 'inputs', string $content = 'summary'): DocumentContext
    {
        $source = new SourceVersion('plan-1', hash('sha256', $inputs), hash('sha256', $content), [], 24, '2026-11-04', '{}');

        return new DocumentContext($source, new FactSet, 'brief', Audience::External);
    }

    private function decision(GateReport $report, RuleResult $rule, Subcheck $check): RiskAcceptance
    {
        return new RiskAcceptance($rule->definition->id, $check->id, $check->hash(), $report->dependencyHash($rule->definition),
            $report->configuration->hash(), 1, 'The disclosed business risk is accepted.', '2026-11-04T12:00:00Z');
    }
}
