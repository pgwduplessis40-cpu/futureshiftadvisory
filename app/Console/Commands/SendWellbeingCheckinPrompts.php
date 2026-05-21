<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Notifications\WellbeingCheckinPromptNotification;
use App\Support\RequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class SendWellbeingCheckinPrompts extends Command
{
    protected $signature = 'wellbeing:send-prompts {--dry-run : Count due prompts without sending notifications}';

    protected $description = 'Send optional monthly wellbeing check-in prompts to client portal users.';

    public function handle(RequestContext $context): int
    {
        $context->apply('system', []);

        $periodStart = now()->startOfMonth()->toDateString();
        $sent = 0;

        Client::query()
            ->with(['teamMembers.user'])
            ->whereHas('teamMembers', function ($query): void {
                $query->whereHas('user', function ($userQuery): void {
                    $userQuery->whereIn('user_type', [
                        User::TYPE_CLIENT_PRIMARY,
                        User::TYPE_CLIENT_TEAM,
                    ]);
                });
            })
            ->orderBy('legal_name')
            ->chunkById(100, function ($clients) use (&$sent, $periodStart): void {
                foreach ($clients as $client) {
                    foreach ($this->dueRecipients($client, $periodStart) as $user) {
                        $sent++;

                        if (! $this->option('dry-run')) {
                            Notification::send($user, new WellbeingCheckinPromptNotification($client, $periodStart));
                        }
                    }
                }
            });

        $this->info("{$sent} wellbeing check-in prompt".($sent === 1 ? '' : 's').' due.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, User>
     */
    private function dueRecipients(Client $client, string $periodStart): array
    {
        return $client->teamMembers
            ->filter(fn (ClientTeamMember $member): bool => $member->user instanceof User
                && in_array($member->user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
                && ! $this->hasCheckin($client, $member->user, $periodStart))
            ->map(fn (ClientTeamMember $member): User => $member->user)
            ->values()
            ->all();
    }

    private function hasCheckin(Client $client, User $user, string $periodStart): bool
    {
        return WellbeingCheckin::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->whereDate('period_start', $periodStart)
            ->exists();
    }
}
