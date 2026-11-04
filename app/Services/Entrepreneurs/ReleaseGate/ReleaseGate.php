<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\PlanGateRun;
use App\Models\PlanRuleDecision;
use App\Models\User;

final readonly class ReleaseGate
{
    public function __construct(private GateContextFactory $contexts, private RulePolicyStore $policies, private GateRunRecorder $recorder) {}

    public function evaluate(BusinessPlan $plan, string $document = 'brief', Audience $audience = Audience::External): GateReport
    {
        $context = $this->contexts->current($plan, $document, $audience);
        if (! $plan->exists) {
            return PhaseOneRules::evaluator()->evaluate($context, new RuleConfiguration);
        }
        $acceptances = [];
        $latest = PlanRuleDecision::query()->where('business_plan_id', $plan->getKey())->orderByDesc('created_at')->orderByDesc('id')->get()
            ->unique(fn (PlanRuleDecision $d): string => $d->getAttribute('rule_id').'|'.$d->getAttribute('subcheck_id'));
        foreach ($latest as $decision) {
            if ($decision->getAttribute('action') !== 'accept') {
                continue;
            }
            $acceptances[] = new RiskAcceptance((string) $decision->getAttribute('rule_id'), (string) $decision->getAttribute('subcheck_id'),
                (string) $decision->getAttribute('finding_hash'), (string) $decision->getAttribute('dependency_hash'),
                (string) $decision->getAttribute('configuration_hash'), (int) $decision->getAttribute('actor_user_id'),
                (string) $decision->getAttribute('reason'), $decision->created_at?->toIso8601String() ?? '');
        }

        return PhaseOneRules::evaluator()->evaluate($context, $this->policies->current(), $acceptances);
    }

    public function record(BusinessPlan $plan, GateReport $report, ?User $actor): PlanGateRun
    {
        return $this->recorder->record($plan, $report, $actor);
    }

    public function assertExternalRelease(BusinessPlan $plan, string $document, ?User $actor): never
    {
        $report = $this->evaluate($plan, $document);
        $this->record($plan, $report, $actor);
        abort(409, 'This document remains Draft. Resolve the release findings and complete the remaining evidence and final-file checks before external issue.');
    }
}
