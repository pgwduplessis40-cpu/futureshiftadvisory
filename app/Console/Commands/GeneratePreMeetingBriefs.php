<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reports\PreMeetingBriefGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GeneratePreMeetingBriefs extends Command
{
    protected $signature = 'briefings:generate-pre-meeting
                            {--now= : Optional ISO timestamp for deterministic 24-hour window checks.}';

    protected $description = 'Generate draft pre-meeting briefs for meetings around 24 hours away.';

    public function handle(PreMeetingBriefGenerator $briefs): int
    {
        $nowInput = $this->option('now');
        $now = is_string($nowInput) && $nowInput !== ''
            ? Carbon::parse($nowInput)
            : null;
        $generated = $briefs->generateDue($now);

        $this->info("{$generated} pre-meeting brief".($generated === 1 ? '' : 's').' generated.');

        return self::SUCCESS;
    }
}
