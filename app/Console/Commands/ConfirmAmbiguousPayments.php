<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\InstallmentPaymentProcessor;
use Illuminate\Console\Command;

final class ConfirmAmbiguousPayments extends Command
{
    protected $signature = 'payments:confirm-ambiguous {--limit=50 : Maximum installment confirmations to process}';

    protected $description = 'Recover stale installment charges and confirm ambiguous gateway outcomes.';

    public function handle(InstallmentPaymentProcessor $payments): int
    {
        $result = $payments->confirmAmbiguous(limit: max(1, (int) $this->option('limit')));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
