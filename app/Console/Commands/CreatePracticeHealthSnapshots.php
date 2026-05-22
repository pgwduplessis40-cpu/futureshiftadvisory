<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Reports\PracticeHealthReport;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class CreatePracticeHealthSnapshots extends Command
{
    protected $signature = 'practice-health:snapshot
                            {--user-id= : Generate one advisor/super-admin snapshot for a specific user.}
                            {--all-advisors : Generate advisor portfolio snapshots in addition to the super-admin practice snapshot.}';

    protected $description = 'Create cached practice health report snapshots.';

    public function handle(PracticeHealthReport $reports, RequestContext $context): int
    {
        $context->apply(RequestContext::ROLE_SUPER_ADMIN, []);

        $userId = $this->option('user-id');

        if (is_numeric($userId)) {
            $user = User::query()->findOrFail((int) $userId);
            $snapshot = $reports->snapshotForUser($user);
            $this->info("Created practice health snapshot {$snapshot->id} for user {$user->id}.");

            return self::SUCCESS;
        }

        $practiceSnapshot = $reports->snapshotForPractice();
        $created = 1;

        if ((bool) $this->option('all-advisors')) {
            User::query()
                ->whereIn('user_type', [
                    User::TYPE_ADVISOR,
                    User::TYPE_JUNIOR_ADVISOR,
                    User::TYPE_ENTREPRENEUR_MENTOR,
                ])
                ->orderBy('id')
                ->each(function (User $user) use ($reports, &$created): void {
                    $reports->snapshotForUser($user);
                    $created++;
                });
        }

        $this->info("Created {$created} practice health snapshot(s). Practice snapshot: {$practiceSnapshot->id}.");

        return self::SUCCESS;
    }
}
