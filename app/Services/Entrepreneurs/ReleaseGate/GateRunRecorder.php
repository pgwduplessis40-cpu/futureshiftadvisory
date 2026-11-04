<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\PlanGateRun;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final readonly class GateRunRecorder
{
    public function __construct(private AuditWriter $audit) {}

    public function record(BusinessPlan $plan, GateReport $report, ?User $actor): PlanGateRun
    {
        if ((string) $plan->getKey() !== $report->context->source->planId) {
            throw new \InvalidArgumentException('Gate run does not belong to this plan.');
        }

        return DB::transaction(function () use ($plan, $report, $actor): PlanGateRun {
            $run = PlanGateRun::query()->create([
                'business_plan_id' => $plan->getKey(), 'document_kind' => $report->context->document,
                'audience' => $report->context->audience->value, 'source_hash' => $report->context->source->hash(),
                'inputs_hash' => $report->context->source->inputsHash, 'context_hash' => $report->context->hash(),
                'configuration_hash' => $report->configuration->hash(), 'required_horizon' => $report->context->source->requiredHorizon,
                'source_payload' => json_decode(CanonicalJson::encode($report->context->source), true, 512, JSON_THROW_ON_ERROR),
                'result_payload' => json_decode(CanonicalJson::encode($report), true, 512, JSON_THROW_ON_ERROR),
                'actor_user_id' => $actor?->getKey(),
            ]);
            $this->audit->record('entrepreneur.release_gate_evaluated', subject: $plan, actor: $actor, after: [
                'run_id' => $run->getKey(), 'context_hash' => $report->context->hash(), 'configuration_hash' => $report->configuration->hash(),
                'document' => $run->document_kind, 'status' => 'draft',
            ]);

            return $run;
        });
    }
}
