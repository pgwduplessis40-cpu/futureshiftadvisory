<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Communications\BulkCommunicationService;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class SendBulkCommunications extends Command
{
    protected $signature = 'communications:bulk-send';

    protected $description = 'Send scheduled bulk communications that are due.';

    public function handle(BulkCommunicationService $communications, RequestContext $context): int
    {
        $context->apply('system', []);

        $sent = $communications->sendDue();

        $this->info($sent->count().' bulk communication'.($sent->count() === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
}
