<?php

declare(strict_types=1);

namespace App\Services\Integration\BusinessTools\Contracts;

use App\Models\NzToolConnection;

interface NzBusinessToolClient
{
    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function businessSnapshot(NzToolConnection $connection, array $token): array;

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(NzToolConnection $connection, array $token): void;
}
