<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OffboardingRecord;
use App\Services\Offboarding\OffboardingService;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class SendReengagementReminders extends Command
{
    protected $signature = 'offboarding:send-reengagement-reminders {--dry-run : Count due reminders without marking them sent}';

    protected $description = 'Send scheduled re-engagement reminders for completed offboarding records.';

    public function handle(RequestContext $context, OffboardingService $offboarding): int
    {
        $context->apply('system', []);

        if ($this->option('dry-run')) {
            $count = OffboardingRecord::query()
                ->whereNull('reengagement_reminder_sent_at')
                ->whereNotNull('reengagement_due')
                ->where('reengagement_due', '<=', now())
                ->count();
            $this->info("{$count} re-engagement reminder".($count === 1 ? '' : 's').' would be due.');

            return self::SUCCESS;
        }

        $sent = $offboarding->sendDueReengagementReminders();
        $this->info("{$sent} re-engagement reminder".($sent === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
}
