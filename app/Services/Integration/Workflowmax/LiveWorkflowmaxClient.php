<?php

declare(strict_types=1);

namespace App\Services\Integration\Workflowmax;

use App\Models\AccountingConnection;
use App\Services\Integration\AccountingLiveClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Workflowmax\Contracts\WorkflowmaxClient;

final class LiveWorkflowmaxClient extends AccountingLiveClient implements WorkflowmaxClient
{
    public function __construct(ResilientHttp $http, FakeWorkflowmaxClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return AccountingConnection::PROVIDER_WORKFLOWMAX;
    }
}
