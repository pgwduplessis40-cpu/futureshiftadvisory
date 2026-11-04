<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Enums\Permission;
use App\Models\BusinessPlan;
use App\Models\PlanGateRun;
use App\Models\PlanRuleDecision;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RiskDecisionStore
{
    public function __construct(private ReleaseGate $gate, private AuditWriter $audit) {}

    public function accept(BusinessPlan $plan, PlanGateRun $run, User $actor, string $ruleId, string $subcheckId, string $reason): PlanRuleDecision
    {
        abort_unless($actor->can(Permission::ENTREPRENEURS_ACCEPT_RELEASE_RISK->value), 403);
        Gate::forUser($actor)->authorize('view', $plan->entrepreneurProfile);
        abort_unless((string) $run->business_plan_id === (string) $plan->getKey(), 404);
        if (mb_strlen(trim($reason)) < 15) {
            throw ValidationException::withMessages(['reason' => 'Explain the risk decision in at least 15 characters.']);
        }

        return DB::transaction(function () use ($plan, $run, $actor, $ruleId, $subcheckId, $reason): PlanRuleDecision {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(76432109)');
            }
            $locked = BusinessPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $locked->budgetRunway()->lockForUpdate()->first();
            $locked->sections()->lockForUpdate()->get();
            $report = $this->gate->evaluate($locked, (string) $run->document_kind, Audience::from((string) $run->audience));
            if (! hash_equals((string) $run->getAttribute('context_hash'), $report->context->hash())
                || ! hash_equals((string) $run->getAttribute('configuration_hash'), $report->configuration->hash())) {
                throw ValidationException::withMessages(['decision' => 'The source or policy changed. Refresh and review the current findings.']);
            }
            $rule = null;
            $check = null;
            foreach ($report->results as $result) {
                if ($result->definition->id !== $ruleId) {
                    continue;
                }
                $rule = $result->definition;
                foreach ($result->subchecks as $candidate) {
                    if ($candidate->id === $subcheckId) {
                        $check = $candidate;
                    }
                }
            }
            if ($rule === null || $check === null || ! $check->status->unresolved() || $check->severity !== Severity::Review
                || ($report->configuration->severity($rule, $check) === Severity::Block && ! $rule->acceptsElevatedRisk())) {
                throw ValidationException::withMessages(['decision' => 'This finding is not eligible for a business-risk acceptance. Integrity findings must be resolved.']);
            }
            $previous = PlanRuleDecision::query()->where('business_plan_id', $plan->getKey())->where('rule_id', $ruleId)
                ->where('subcheck_id', $subcheckId)->orderByDesc('created_at')->orderByDesc('id')->first();
            $record = PlanRuleDecision::query()->create([
                'business_plan_id' => $plan->getKey(), 'plan_gate_run_id' => $run->getKey(), 'rule_id' => $ruleId, 'subcheck_id' => $subcheckId,
                'finding_hash' => $check->hash(), 'dependency_hash' => $report->dependencyHash($rule), 'configuration_hash' => $report->configuration->hash(),
                'action' => 'accept', 'supersedes_id' => $previous?->getKey(), 'reason' => trim($reason), 'actor_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('entrepreneur.release_risk_accepted', subject: $plan, actor: $actor, after: [
                'decision_id' => $record->getKey(), 'run_id' => $run->getKey(), 'rule_id' => $ruleId, 'subcheck_id' => $subcheckId,
                'finding_hash' => $check->hash(), 'dependency_hash' => $report->dependencyHash($rule), 'reason' => trim($reason),
            ]);

            return $record;
        }, 3);
    }
}
