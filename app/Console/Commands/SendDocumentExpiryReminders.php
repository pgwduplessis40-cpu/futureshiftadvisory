<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Documents\DocumentExpiryReminderService;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class SendDocumentExpiryReminders extends Command
{
    protected $signature = 'documents:expiry-reminders {--days=30 : Reminder lookahead window in days}';

    protected $description = 'Send idempotent reminders for clean client documents approaching expiry.';

    public function handle(DocumentExpiryReminderService $reminders, RequestContext $context): int
    {
        $context->apply('system', []);

        $sent = $reminders->sendDue((int) $this->option('days'));

        $this->info($sent.' document expiry reminder'.($sent === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
}
