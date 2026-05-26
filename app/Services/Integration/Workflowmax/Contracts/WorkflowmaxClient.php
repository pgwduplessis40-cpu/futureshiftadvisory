<?php

declare(strict_types=1);

namespace App\Services\Integration\Workflowmax\Contracts;

use App\Models\AccountingConnection;

interface WorkflowmaxClient
{
    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function financialSnapshot(AccountingConnection $connection, array $token): array;

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(AccountingConnection $connection, array $token): void;
}
