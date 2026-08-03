<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalHealthCheckResult;
use App\Models\OperationalHealthCheckRun;
use App\Services\OperationalHealth\OperationalHealthAlerter;
use App\Services\OperationalHealth\OperationalHealthCheckRunner;
use Database\Seeders\OperationalHealthFixtureSeeder;
use Illuminate\Console\Command;

final class RunOperationalHealthChecks extends Command
{
    protected $signature = 'fsa:operational-health-check
                            {--ensure-fixtures : Provision idempotent monitor fixtures before running checks.}
                            {--sentinel : Run only the low-cost always-on client-facing checks.}
                            {--fail-on-warning : Return a non-zero exit code when checks are skipped or warning.}';

    protected $description = 'Run synthetic application checks and record operational health findings.';

    public function handle(OperationalHealthCheckRunner $runner, OperationalHealthAlerter $alerter): int
    {
        if ((bool) config('operational_health.ensure_fixtures', true) || (bool) $this->option('ensure-fixtures')) {
            $this->callSilent('db:seed', [
                '--class' => OperationalHealthFixtureSeeder::class,
                '--force' => true,
            ]);
        }

        $scope = $this->option('sentinel')
            ? OperationalHealthCheckRunner::SCOPE_SENTINEL
            : OperationalHealthCheckRunner::SCOPE_FULL;
        $run = $runner->run($scope);
        $notifications = $alerter->notify($run);

        $this->info(sprintf(
            'Operational health %s check %s: %d passed, %d failed, %d warning, %d skipped.',
            $scope,
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

        if ($notifications > 0) {
            $this->warn(sprintf('Sent %d urgent operational health notification%s.', $notifications, $notifications === 1 ? '' : 's'));
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
