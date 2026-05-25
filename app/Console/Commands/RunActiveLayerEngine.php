<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\ActiveLayerEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunActiveLayerEngine extends Command
{
    protected $signature = 'learning:active-layers
                            {--activate : Activate all registered layers before running.}
                            {--layer=* : Optional layer id(s) to force run.}
                            {--at= : Optional ISO-8601 timestamp for deterministic runs.}';

    protected $description = 'Run active learning layers as governed candidate-only updates.';

    public function handle(ActiveLayerEngine $engine): int
    {
        $atInput = $this->option('at');
        $at = is_string($atInput) && $atInput !== ''
            ? Carbon::parse($atInput)
            : now();

        if ((bool) $this->option('activate')) {
            $activated = $engine->activateAll($at);
            $this->info("Activated {$activated->count()} learning layer(s).");
        }

        $layerIds = collect($this->option('layer'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $runs = $engine->runDue($at, $layerIds);

        $this->info("Recorded {$runs->count()} active learning run(s).");

        return self::SUCCESS;
    }
}
