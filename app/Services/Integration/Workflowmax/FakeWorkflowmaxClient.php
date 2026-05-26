<?php

declare(strict_types=1);

namespace App\Services\Integration\Workflowmax;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingFixtureHelpers;
use App\Services\Integration\Workflowmax\Contracts\WorkflowmaxClient;

final class FakeWorkflowmaxClient implements WorkflowmaxClient
{
    use AccountingFixtureHelpers;

    protected function fixture(string $key): array
    {
        return $this->fixtures->find('workflowmax-accounting', "default.{$key}");
    }

    protected function providerName(): string
    {
        return AccountingConnection::PROVIDER_WORKFLOWMAX;
    }
}
