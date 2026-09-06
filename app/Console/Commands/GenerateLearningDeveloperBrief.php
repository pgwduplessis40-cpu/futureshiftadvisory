<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\LearningRecommendationWorkflow;
use Illuminate\Console\Command;

final class GenerateLearningDeveloperBrief extends Command
{
    protected $signature = 'learning:developer-brief';

    protected $description = 'Generate the governed learning recommendation brief for the development team.';

    public function handle(LearningRecommendationWorkflow $recommendations): int
    {
        $this->line($recommendations->developerBrief());

        return self::SUCCESS;
    }
}
