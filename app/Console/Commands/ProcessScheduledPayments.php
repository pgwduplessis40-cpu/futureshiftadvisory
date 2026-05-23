<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\PaymentProcessor;
use Illuminate\Console\Command;

final class ProcessScheduledPayments extends Command
{
    protected $signature = 'payments:process-scheduled
                            {--limit=50 : Maximum due schedules to process.}';

    protected $description = 'Process due payment schedules and generate receipts for successful charges.';

    public function handle(PaymentProcessor $processor): int
    {
        $result = $processor->processDue(limit: (int) $this->option('limit'));

        $this->info(sprintf(
            'Processed %d due schedule(s): %d succeeded, %d retrying, %d failed, %d receipt(s).',
            $result['scanned'],
            $result['succeeded'],
            $result['retrying'],
            $result['failed'],
            $result['receipts'],
        ));

        return self::SUCCESS;
    }
}
