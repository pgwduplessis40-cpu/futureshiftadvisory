<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\OperationalHealthFixtureSeeder;
use Illuminate\Console\Command;

final class SeedOperationalHealthFixtures extends Command
{
    protected $signature = 'fsa:seed-operational-health-fixtures
                            {--run-check : Run the operational health checks after provisioning fixtures.}';

    protected $description = 'Provision idempotent synthetic fixtures required by the operational health workflow checks.';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => OperationalHealthFixtureSeeder::class,
            '--force' => true,
        ]);

        $this->info('Operational health fixtures are provisioned.');

        if ($this->option('run-check')) {
            return (int) $this->call(RunOperationalHealthChecks::class);
        }

        return self::SUCCESS;
    }
}
