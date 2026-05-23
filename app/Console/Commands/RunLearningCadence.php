<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\LayerCadenceRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunLearningCadence extends Command
{
    protected $signature = 'learning:cadence
                            {--layer=* : Optional layer id(s) to force record.}
                            {--at= : Optional ISO-8601 timestamp for deterministic runs.}';

    protected $description = 'Record due governed learning-layer cadence runs.';

    public function handle(LayerCadenceRunner $runner): int
    {
        $atInput = $this->option('at');
        $at = is_string($atInput) && $atInput !== ''
            ? Carbon::parse($atInput)
            : null;
        $layerIds = collect($this->option('layer'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $runs = $runner->recordDueRuns($at, $layerIds);

        $this->info("Recorded {$runs->count()} learning cadence run(s).");

        return self::SUCCESS;
    }
}
