<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\CrossClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunCrossClientIntelligence extends Command
{
    protected $signature = 'intelligence:cross-client
                            {--window-days=90 : Rolling findings window in days.}
                            {--generated-at= : Optional ISO-8601 generation timestamp.}';

    protected $description = 'Generate anonymised cross-client industry intelligence signals.';

    public function handle(CrossClient $crossClient): int
    {
        $generatedAtInput = $this->option('generated-at');
        $generatedAt = is_string($generatedAtInput) && $generatedAtInput !== ''
            ? Carbon::parse($generatedAtInput)
            : null;

        $signals = $crossClient->run(
            windowDays: (int) $this->option('window-days'),
            generatedAt: $generatedAt,
        );

        $this->info("Generated {$signals->count()} cross-client intelligence signal(s).");

        return self::SUCCESS;
    }
}
