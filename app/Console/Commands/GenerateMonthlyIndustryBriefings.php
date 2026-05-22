<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\Reports\IndustryBriefingGenerator;
use App\Support\RequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GenerateMonthlyIndustryBriefings extends Command
{
    protected $signature = 'briefings:generate-monthly
                            {--period= : Optional month/ISO date for deterministic runs.}';

    protected $description = 'Generate draft monthly industry briefings for each client.';

    public function handle(IndustryBriefingGenerator $briefings, RequestContext $context): int
    {
        $context->apply('system', []);

        $periodInput = $this->option('period');
        $period = is_string($periodInput) && $periodInput !== ''
            ? Carbon::parse($periodInput)->startOfMonth()
            : now()->startOfMonth();
        $generated = 0;

        foreach (Client::query()->orderBy('legal_name')->cursor() as $client) {
            $briefing = $briefings->generate($client, $period);
            if ($briefing->wasRecentlyCreated) {
                $generated++;
            }
        }

        $this->info("{$generated} monthly industry briefing draft".($generated === 1 ? '' : 's').' generated.');

        return self::SUCCESS;
    }
}
