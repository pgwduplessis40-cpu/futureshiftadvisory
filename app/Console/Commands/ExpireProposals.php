<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Proposals\ProposalBuilder;
use Illuminate\Console\Command;

final class ExpireProposals extends Command
{
    protected $signature = 'proposals:expire';

    protected $description = 'Expire released proposals whose release window has elapsed.';

    public function handle(ProposalBuilder $proposals): int
    {
        $expired = $proposals->expireDue(now());

        $this->info("{$expired} proposal".($expired === 1 ? '' : 's').' expired.');

        return self::SUCCESS;
    }
}
