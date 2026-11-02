<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Jobs\GenerateEligibleExecutiveSummary;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ExecutiveSummaryRecovery
{
    public function state(string $assessmentId): string
    {
        $state = Cache::get($this->key($assessmentId));

        return is_string($state) ? $state : 'idle';
    }

    public function mark(string $assessmentId, string $state): void
    {
        Cache::put($this->key($assessmentId), $state, now()->addMinutes(15));
    }

    public function request(PlanAssessment $assessment, ?User $actor = null): bool
    {
        $id = (string) $assessment->getKey();

        return (bool) Cache::lock($this->key($id).':request', 10)->get(function () use ($assessment, $actor, $id): bool {
            if (in_array($this->state($id), ['queued', 'running', 'retrying'], true)) {
                return false;
            }
            $this->mark($id, 'queued');
            try {
                GenerateEligibleExecutiveSummary::dispatch($id)->afterCommit();
            } catch (Throwable $exception) {
                $this->mark($id, 'failed');
                throw $exception;
            }
            app(AuditWriter::class)->record('entrepreneur.executive_summary_retry_requested', subject: $assessment, actor: $actor);

            return true;
        });
    }

    private function key(string $id): string
    {
        return 'entrepreneur-executive-summary-state:'.$id;
    }
}
