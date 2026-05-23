<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Models\AnalysisFinding;
use App\Models\BusinessPlan;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlanBuilder
{
    public function __construct(
        private readonly SharedPlanBuilder $plans,
        private readonly AuditWriter $audit,
    ) {}

    public function buildFromWorkstreams(DdEngagement $engagement, ?User $actor = null): BusinessPlan
    {
        $engagement->loadMissing('client');

        return DB::transaction(function () use ($engagement, $actor): BusinessPlan {
            $plan = $this->plans->createOrUpdate($engagement->client, [
                'dd_engagement_id' => $engagement->getKey(),
                'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
                'title' => 'Acquisition plan: '.$engagement->target_name,
            ], $actor);

            $this->upsertFoundation($plan, $engagement);
            $this->upsertValuation($plan, $engagement);
            $this->upsertWorkstreamFindings($plan, $engagement);
            $this->upsertStrategySummary($plan, $engagement);

            $this->audit->record('dd.plan_built', subject: $plan, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'sections' => $plan->sections()->count(),
                'completion' => $this->plans->completion($plan->refresh()),
            ]);

            return $plan->refresh()->load('phases.sections.sourceFinding');
        });
    }

    public function markAcquisitionProceeding(DdEngagement $engagement, ?User $actor = null): BusinessPlan
    {
        $plan = $this->buildFromWorkstreams($engagement, $actor);
        $this->plans->assertComplete($plan);

        return DB::transaction(function () use ($engagement, $actor, $plan): BusinessPlan {
            $payload = $this->plans->foundingPayload($plan);
            $plan->forceFill([
                'status' => BusinessPlan::STATUS_FOUNDING,
                'founding_advisory_payload' => $payload,
                'completed_at' => now(),
            ])->save();

            $engagement->forceFill([
                'status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
            ])->save();

            $this->audit->record('dd.plan_founding_advisory_ready', subject: $plan, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'engagement_status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
                'phase_count' => count($payload['phases']),
            ]);

            return $plan->refresh()->load('phases.sections');
        });
    }

    private function upsertFoundation(BusinessPlan $plan, DdEngagement $engagement): void
    {
        $details = $engagement->target_details ?? [];
        $body = sprintf(
            'Acquisition target: %s. Industry: %s. NZBN: %s. DD engagement status: %s.',
            $engagement->target_name,
            (string) ($details['industry'] ?? 'not supplied'),
            (string) ($details['nzbn'] ?? 'not supplied'),
            $engagement->status,
        );

        $this->plans->upsertSection(
            plan: $plan,
            phaseKey: 'foundation',
            key: 'dd-foundation-target',
            title: 'Acquisition target foundation',
            body: $body,
            sourceType: BusinessPlan::SOURCE_DUE_DILIGENCE,
            metadata: [
                'dd_engagement_id' => $engagement->id,
                'target_details' => $details,
            ],
        );
    }

    private function upsertValuation(BusinessPlan $plan, DdEngagement $engagement): void
    {
        $valuation = DdValuation::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->latest('as_at')
            ->first();

        if (! $valuation instanceof DdValuation) {
            return;
        }

        $body = sprintf(
            'DD valuation range in NZD: %s low, %s mid, %s high. Buyer position: %s.',
            $this->money(data_get($valuation->normalised_values, 'reconciled.low')),
            $this->money(data_get($valuation->normalised_values, 'reconciled.mid')),
            $this->money(data_get($valuation->normalised_values, 'reconciled.high')),
            str_replace('_', ' ', (string) data_get($valuation->buyer_position, 'position')),
        );

        $this->plans->upsertSection(
            plan: $plan,
            phaseKey: 'financial',
            key: 'dd-valuation-summary',
            title: 'DD valuation summary',
            body: $body,
            sourceType: BusinessPlan::SOURCE_DUE_DILIGENCE,
            metadata: [
                'dd_valuation_id' => $valuation->id,
                'buyer_position' => $valuation->buyer_position,
            ],
        );
    }

    private function upsertWorkstreamFindings(BusinessPlan $plan, DdEngagement $engagement): void
    {
        $workstreams = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->with('analysisRun.findings')
            ->get();

        foreach ($workstreams as $workstream) {
            $findings = $workstream->analysisRun?->findings ?? collect();

            foreach ($findings as $finding) {
                if (! $finding instanceof AnalysisFinding) {
                    continue;
                }

                $this->plans->upsertSection(
                    plan: $plan,
                    phaseKey: $this->phaseForWorkstream((string) $workstream->workstream),
                    key: $this->sectionKey($workstream, $finding),
                    title: $finding->title,
                    body: $finding->body,
                    sourceType: BusinessPlan::SOURCE_DUE_DILIGENCE,
                    finding: $finding,
                    metadata: [
                        'dd_workstream_id' => $workstream->id,
                        'workstream' => $workstream->workstream,
                        'document_support' => $finding->document_support,
                    ],
                );
            }
        }
    }

    private function upsertStrategySummary(BusinessPlan $plan, DdEngagement $engagement): void
    {
        $completed = DdWorkstream::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->pluck('workstream')
            ->values()
            ->all();

        if ($completed === []) {
            return;
        }

        $this->plans->upsertSection(
            plan: $plan,
            phaseKey: 'strategy',
            key: 'dd-strategy-integration',
            title: 'Acquisition strategy integration',
            body: 'Completed DD workstreams informing strategy: '.implode(', ', array_map(static fn (string $workstream): string => str_replace('_', ' ', $workstream), $completed)).'.',
            sourceType: BusinessPlan::SOURCE_DUE_DILIGENCE,
            metadata: [
                'completed_workstreams' => $completed,
            ],
        );
    }

    private function phaseForWorkstream(string $workstream): string
    {
        return match ($workstream) {
            'commercial_market' => 'market',
            'valuation', 'financial' => 'financial',
            'legal', 'tax', 'operational', 'hr_people', 'nz_regulatory' => 'legal_operations',
            default => 'strategy',
        };
    }

    private function sectionKey(DdWorkstream $workstream, AnalysisFinding $finding): string
    {
        return Str::limit("dd-{$workstream->workstream}-{$finding->id}", 160, '');
    }

    private function money(mixed $value): string
    {
        return 'NZD '.number_format(is_numeric($value) ? (float) $value : 0.0, 0);
    }
}
