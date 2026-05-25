<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\SharedLayer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunSharedIntelligenceLayer extends Command
{
    protected $signature = 'intelligence:shared-layer
                            {--generated-at= : Optional ISO-8601 generation timestamp.}';

    protected $description = 'Generate anonymised advisory/entrepreneur shared intelligence patterns.';

    public function handle(SharedLayer $sharedLayer): int
    {
        $generatedAtInput = $this->option('generated-at');
        $generatedAt = is_string($generatedAtInput) && $generatedAtInput !== ''
            ? Carbon::parse($generatedAtInput)
            : null;

        $patterns = $sharedLayer->run($generatedAt);

        $this->info("Generated {$patterns->count()} shared intelligence pattern(s).");

        return self::SUCCESS;
    }
}
