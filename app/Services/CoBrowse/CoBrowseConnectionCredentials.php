<?php

declare(strict_types=1);

namespace App\Services\CoBrowse;

use App\Models\CoBrowseConnection;

final readonly class CoBrowseConnectionCredentials
{
    public function __construct(
        public CoBrowseConnection $connection,
        public string $secret,
    ) {}

    /**
     * @return array{connection_id:string,connection_secret:string,channel:string,expires_at:string}
     */
    public function toPayload(): array
    {
        return [
            'connection_id' => (string) $this->connection->getKey(),
            'connection_secret' => $this->secret,
            'channel' => 'co-browse.connection.'.(string) $this->connection->getKey(),
            'expires_at' => $this->connection->expires_at->toIso8601String(),
        ];
    }
}
