<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Client;
use App\Services\Clients\LifecycleManager;
use LogicException;

final class ClientLifecycleObserver
{
    public function updating(Client $client): void
    {
        if (! $client->isDirty('status')) {
            return;
        }

        if (LifecycleManager::statusMutationIsAllowed()) {
            return;
        }

        throw new LogicException('Client status transitions must go through LifecycleManager.');
    }
}
