<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReferenceData\ReferenceDataFreshness;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class CheckReferenceDataFreshness extends Command
{
    protected $signature = 'reference-data:check-freshness {--dry-run : Count due tasks without sending notifications}';

    protected $description = 'Check implemented reference-data freshness and notify super-admins about due updates.';

    public function handle(ReferenceDataFreshness $freshness, RequestContext $context): int
    {
        $context->apply(RequestContext::ROLE_SUPER_ADMIN, []);

        $recipients = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();
        $dashboard = $freshness->dashboard();
        $dueCount = count($dashboard['items']);

        if ((bool) $this->option('dry-run')) {
            $this->info("{$dueCount} reference-data task(s) due for {$recipients->count()} super-admin recipient(s).");

            return self::SUCCESS;
        }

        $sent = $freshness->syncNotifications($recipients);

        $this->info("Reference-data freshness checked: {$dueCount} task(s) due, {$sent} notification(s) sent.");

        return self::SUCCESS;
    }
}
