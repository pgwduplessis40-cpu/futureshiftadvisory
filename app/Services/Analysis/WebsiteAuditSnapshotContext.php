<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Client;
use App\Models\WebsiteAuditSnapshot;

final class WebsiteAuditSnapshotContext
{
    private ?WebsiteAuditSnapshot $snapshot = null;

    public function bind(WebsiteAuditSnapshot $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function forClient(Client $client): ?WebsiteAuditSnapshot
    {
        if ($this->snapshot === null || $this->snapshot->client_id !== $client->getKey()) {
            return null;
        }

        return $this->snapshot;
    }

    public function clear(): void
    {
        $this->snapshot = null;
    }
}
