<?php

declare(strict_types=1);

namespace App\Services\Integration\Workflowmax;

use App\Models\AccountingConnection;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Workflowmax\Contracts\WorkflowmaxClient;

final class FallbackWorkflowmaxClient implements WorkflowmaxClient
{
    public function __construct(
        private readonly LiveWorkflowmaxClient $live,
        private readonly FakeWorkflowmaxClient $fake,
    ) {}

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            return $this->live->exchangeCodeForToken($code, $redirectUri);
        } catch (IntegrationDisabledException) {
            return $this->fake->exchangeCodeForToken($code, $redirectUri);
        }
    }

    public function financialSnapshot(AccountingConnection $connection, array $token): array
    {
        try {
            return $this->live->financialSnapshot($connection, $token);
        } catch (IntegrationDisabledException) {
            return $this->fake->financialSnapshot($connection, $token);
        }
    }

    public function revoke(AccountingConnection $connection, array $token): void
    {
        try {
            $this->live->revoke($connection, $token);
        } catch (IntegrationDisabledException) {
            $this->fake->revoke($connection, $token);
        }
    }
}
