<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Client;
use App\Models\DocumentVerification;
use Illuminate\Support\Collection;

final class DocumentVerificationGate
{
    public function allowsAnalysis(Client|string $client): bool
    {
        return $this->blockingFlags($client)->isEmpty();
    }

    public function ensureClear(Client|string $client): void
    {
        $flags = $this->blockingFlags($client);

        if ($flags->isNotEmpty()) {
            throw new DocumentVerificationBlockedException($flags);
        }
    }

    /**
     * @return Collection<int, DocumentVerification>
     */
    public function blockingFlags(Client|string $client): Collection
    {
        $clientId = $client instanceof Client ? (string) $client->getKey() : $client;

        return DocumentVerification::query()
            ->outstandingFlags()
            ->where('client_id', $clientId)
            ->with('document')
            ->latest()
            ->get();
    }
}
