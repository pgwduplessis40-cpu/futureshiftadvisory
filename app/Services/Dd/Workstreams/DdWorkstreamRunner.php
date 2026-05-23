<?php

declare(strict_types=1);

namespace App\Services\Dd\Workstreams;

use App\Models\AnalysisRun;
use App\Models\DdEngagement;
use App\Models\DdWorkstream;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\DataRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DdWorkstreamRunner
{
    public function __construct(
        private readonly AnalysisRunner $analysis,
        private readonly DdEvidenceAssembler $evidence,
        private readonly DdNzCheckProvider $nzChecks,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return Collection<int, DdWorkstream>
     */
    public function runAll(DdEngagement $engagement, ?User $actor = null): Collection
    {
        return collect(array_keys(DataRoom::WORKSTREAMS))
            ->map(fn (string $workstream): DdWorkstream => $this->run($engagement, $workstream, $actor))
            ->values();
    }

    public function run(DdEngagement $engagement, string $workstream, ?User $actor = null): DdWorkstream
    {
        $engagement->loadMissing('client');
        $workstream = $this->normaliseWorkstream($workstream);
        $evidence = $this->evidence->summary($engagement, $workstream);
        $nzChecks = $this->nzChecks->for($engagement, $workstream);

        return DB::transaction(function () use ($engagement, $workstream, $actor, $evidence, $nzChecks): DdWorkstream {
            $record = DdWorkstream::query()->updateOrCreate(
                [
                    'dd_engagement_id' => $engagement->getKey(),
                    'workstream' => $workstream,
                ],
                [
                    'client_id' => $engagement->client_id,
                    'status' => DdWorkstream::STATUS_RUNNING,
                    'data_room_item_ids' => $evidence['data_room_item_ids'],
                    'verification_weight' => $evidence['verification_weight'],
                    'nz_checks' => $nzChecks,
                    'paused_reason' => null,
                    'ran_by_user_id' => $actor?->getKey(),
                    'ran_at' => now(),
                ],
            );

            if ($evidence['accuracy_discrepancies'] > 0) {
                return $this->pauseForAccuracyDiscrepancy($record, $actor, $evidence);
            }

            $run = $this->analysis->run(
                client: $engagement->client,
                module: new DdWorkstreamModule($engagement, $workstream, $evidence, $nzChecks),
                options: [
                    'actor' => $actor,
                    'created_by_user_id' => $actor?->getKey(),
                    'skip_document_gate' => true,
                    'skip_data_quality_gate' => true,
                ],
            );

            $status = $run->status === AnalysisRun::STATUS_COMPLETED
                ? DdWorkstream::STATUS_COMPLETED
                : ($run->status === AnalysisRun::STATUS_FAILED ? DdWorkstream::STATUS_FAILED : DdWorkstream::STATUS_PAUSED);

            $record->forceFill([
                'status' => $status,
                'analysis_run_id' => $run->getKey(),
                'paused_reason' => $status === DdWorkstream::STATUS_PAUSED ? $run->status : null,
                'ran_at' => now(),
            ])->save();

            $this->audit->record(
                action: $status === DdWorkstream::STATUS_COMPLETED ? 'dd.workstream_completed' : 'dd.workstream_not_completed',
                subject: $record,
                actor: $actor,
                after: [
                    'workstream' => $workstream,
                    'analysis_run_id' => $run->getKey(),
                    'analysis_status' => $run->status,
                    'verification_weight' => $evidence['verification_weight'],
                    'nz_checks' => array_keys($nzChecks),
                ],
            );

            return $record->refresh()->load('analysisRun.findings');
        });
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function pauseForAccuracyDiscrepancy(DdWorkstream $record, ?User $actor, array $evidence): DdWorkstream
    {
        $record->forceFill([
            'status' => DdWorkstream::STATUS_PAUSED,
            'analysis_run_id' => null,
            'paused_reason' => DdWorkstream::PAUSED_ACCURACY_DISCREPANCY,
            'ran_at' => now(),
        ])->save();

        $this->audit->record('dd.workstream_paused', subject: $record, actor: $actor, after: [
            'workstream' => $record->workstream,
            'paused_reason' => DdWorkstream::PAUSED_ACCURACY_DISCREPANCY,
            'accuracy_verification_ids' => $evidence['accuracy_verification_ids'] ?? [],
        ]);

        return $record->refresh();
    }

    private function normaliseWorkstream(string $workstream): string
    {
        $normalised = Str::of($workstream)->lower()->replace(['-', ' '], '_')->value();

        if (! array_key_exists($normalised, DataRoom::WORKSTREAMS)) {
            throw new InvalidArgumentException("Unknown DD workstream [{$workstream}].");
        }

        return $normalised;
    }
}
