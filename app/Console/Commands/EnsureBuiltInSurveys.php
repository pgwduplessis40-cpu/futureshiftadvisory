<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Surveys\SurveyLibrary;
use Illuminate\Console\Command;

final class EnsureBuiltInSurveys extends Command
{
    protected $signature = 'fsa:ensure-built-in-surveys
                            {--service-only : Only reconcile the service improvement survey template.}';

    protected $description = 'Create or reconcile the built-in survey templates without relying on admin page views.';

    public function handle(SurveyLibrary $library): int
    {
        if (! $this->option('service-only')) {
            $default = $library->ensureDefault();
            $this->info(sprintf('Default survey ready: %s v%s (%s).', $default->key, $default->version, $default->status?->value));
        }

        $service = $library->ensureServiceImprovement();
        $this->info(sprintf('Service survey ready: %s v%s (%s).', $service->key, $service->version, $service->status?->value));

        return self::SUCCESS;
    }
}
