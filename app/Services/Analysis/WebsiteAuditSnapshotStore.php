<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Client;
use App\Models\WebsiteAuditSnapshot;
use App\Models\WebsiteUrlConfirmation;

final class WebsiteAuditSnapshotStore
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Client $client, array $attributes): WebsiteAuditSnapshot
    {
        return WebsiteAuditSnapshot::query()->create([
            'client_id' => $client->getKey(),
            ...$attributes,
        ]);
    }

    public function skip(Client $client, string $reason): WebsiteAuditSnapshot
    {
        return $this->create($client, [
            'fetch_status' => WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL,
            'skip_reason' => $reason,
            'pages' => [],
            'ai_evidence' => [],
            'technical' => ['measured' => false],
            'performance' => ['measured' => false],
            'nz_compliance' => ['measured' => false],
            'scores' => ['overall' => null],
            'source_attributions' => [],
        ]);
    }

    public function latestForClient(Client $client): ?WebsiteAuditSnapshot
    {
        return WebsiteAuditSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('fetched_at')
            ->latest()
            ->first();
    }

    public function latestForConfirmation(WebsiteUrlConfirmation $confirmation): ?WebsiteAuditSnapshot
    {
        return WebsiteAuditSnapshot::query()
            ->where('website_url_confirmation_id', $confirmation->getKey())
            ->latest('fetched_at')
            ->latest()
            ->first();
    }
}
