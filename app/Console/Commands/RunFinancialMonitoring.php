<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\HealthMonitor;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;

final class RunFinancialMonitoring extends Command
{
    protected $signature = 'financial-monitoring:run
                            {--cadence=daily : Monitoring cadence label, daily or weekly.}
                            {--force : Run even when FEATURE_CONTINUOUS_MONITORING is disabled.}';

    protected $description = 'Pull connected accounting snapshots and raise early financial-health alerts.';

    public function handle(HealthMonitor $monitor): int
    {
        if (! (bool) config('integrations.accounting.monitoring.enabled', false) && ! $this->option('force')) {
            $this->info('Continuous financial monitoring is disabled.');

            return self::SUCCESS;
        }

        $cadence = (string) $this->option('cadence');
        $this->validateCadence($cadence);

        $result = $monitor->run($cadence);

        $this->info(sprintf(
            'Financial monitoring completed: %d connection(s), %d snapshot(s), %d alert(s), %d failure(s).',
            $result['connections_scanned'],
            $result['snapshots_pulled'],
            $result['alerts_created'],
            $result['failures'],
        ));

        return self::SUCCESS;
    }

    private function validateCadence(string $cadence): void
    {
        validator(
            ['cadence' => $cadence],
            ['cadence' => ['required', Rule::in(['daily', 'weekly'])]],
        )->validate();
    }
}
