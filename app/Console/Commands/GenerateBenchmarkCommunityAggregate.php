<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkCommunity;
use Illuminate\Console\Command;

final class GenerateBenchmarkCommunityAggregate extends Command
{
    protected $signature = 'intelligence:benchmark-community
                            {domain : sme or entrepreneur}
                            {industry=general : Industry code/label}
                            {--quarter= : Optional quarter label, e.g. 2026-Q2}';

    protected $description = 'Generate an anonymous benchmark-community aggregate.';

    public function handle(BenchmarkCommunity $community): int
    {
        $quarter = $this->option('quarter');
        $aggregate = $community->aggregate(
            domain: (string) $this->argument('domain'),
            industryCode: (string) $this->argument('industry'),
            quarter: is_string($quarter) && $quarter !== '' ? $quarter : null,
        );

        $this->info("Generated benchmark aggregate {$aggregate->id}.");

        return self::SUCCESS;
    }
}
