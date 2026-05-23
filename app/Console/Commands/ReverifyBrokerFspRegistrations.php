<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Panels\Broker\BrokerFspVerifier;
use Illuminate\Console\Command;

final class ReverifyBrokerFspRegistrations extends Command
{
    protected $signature = 'panels:broker-fsp-reverify {--days=30 : Re-check brokers last verified at least this many days ago}';

    protected $description = 'Re-verify active broker panel FSP registrations and suspend lapsed brokers.';

    public function handle(BrokerFspVerifier $verifier): int
    {
        $result = $verifier->reverifyDue((int) $this->option('days'));

        $this->info(sprintf(
            'Checked %d broker FSP registrations: %d current, %d suspended.',
            $result['checked'],
            $result['current'],
            $result['suspended'],
        ));

        return self::SUCCESS;
    }
}
