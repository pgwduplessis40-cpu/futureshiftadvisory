<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Services\OperationalHealth\OperationalHealthCheckRunner;
use Illuminate\Console\Command;

final class RunOperationalHealthChecks extends Command
{
    protected $signature = 'fsa:operational-health-check
                            {--fail-on-warning : Return a non-zero exit code when checks are skipped or warning.}';

    protected $description = 'Run daily synthetic application checks and record operational health findings.';

    public function handle(OperationalHealthCheckRunner $runner): int
    {
        $run = $runner->run();

        $this->info(sprintf(
            'Operational health check %s: %d passed, %d failed, %d warning, %d skipped.',
            $run->status,
            $run->passed_checks,
            $run->failed_checks,
            $run->warning_checks,
            $run->skipped_checks,
        ));

        $attention = $run->results
            ->filter(fn (OperationalHealthCheckResult $result): bool => $result->needsAttention())
            ->map(fn (OperationalHealthCheckResult $result): array => [
                $result->status,
                $result->check_key,
                $result->actual_status ?? 'n/a',
                $result->issue_summary ?? 'No issue summary recorded.',
            ])
            ->values()
            ->all();

        if ($attention !== []) {
            $this->table(['Status', 'Check', 'HTTP', 'Issue'], $attention);
        }

        if ($run->status === OperationalHealthCheckRun::STATUS_FAILED) {
            return self::FAILURE;
        }

        if ($this->option('fail-on-warning') && $run->status === OperationalHealthCheckRun::STATUS_WARNING) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
