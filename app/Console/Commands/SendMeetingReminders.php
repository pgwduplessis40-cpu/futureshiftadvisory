<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\Support\RequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders
        {--dry-run : Count due reminders without sending notifications}
        {--window-hours=24 : Send reminders for meetings due within this many hours}';

    protected $description = 'Send notification-centre reminders for upcoming FSA meetings.';

    public function handle(RequestContext $context): int
    {
        $context->apply('system', []);

        $windowHours = max(1, (int) $this->option('window-hours'));
        $due = Meeting::query()
            ->with(['client', 'createdBy'])
            ->where('status', Meeting::STATUS_SCHEDULED)
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addHours($windowHours)])
            ->orderBy('scheduled_at')
            ->limit(250)
            ->get();

        if ((bool) $this->option('dry-run')) {
            $this->info($due->count().' meeting reminder(s) due.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($due as $meeting) {
            $recipient = $meeting->createdBy;
            if (! $recipient instanceof User) {
                continue;
            }

            Notification::send($recipient, new MeetingReminderNotification($meeting));
            $meeting->forceFill(['reminder_sent_at' => now()])->save();
            $sent++;
        }

        $this->info($sent.' meeting reminder(s) sent.');

        return self::SUCCESS;
    }
}
