<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\EntrepreneurStreak;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class RecomputeEntrepreneurStreaks extends Command
{
    protected $signature = 'entrepreneurs:recompute-streaks
        {profile? : Optional entrepreneur profile UUID to recompute.}';

    protected $description = 'Recompute active entrepreneur gamification streak counters.';

    public function handle(EntrepreneurStreak $streak, RequestContext $context): int
    {
        $context->apply('system', []);

        $profileId = $this->argument('profile');
        $query = EntrepreneurProfile::query()
            ->where('gamification_on', true)
            ->orderBy('id');

        if (is_string($profileId) && $profileId !== '') {
            $query->whereKey($profileId);
        }

        $count = 0;
        $query->each(function (EntrepreneurProfile $profile) use ($streak, &$count): void {
            $streak->recompute($profile);
            $count++;
        });

        if ($count === 0 && is_string($profileId) && $profileId !== '') {
            $this->error('Entrepreneur profile not found or gamification is disabled.');

            return self::FAILURE;
        }

        $this->info("Recomputed {$count} entrepreneur streak profile(s).");

        return self::SUCCESS;
    }
}
