<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\FeeMethod;
use App\Models\FeeCalculation;
use App\Models\IntegrationScope;
use App\Models\Proposal;
use InvalidArgumentException;

final class IntegrationScopeProposalGuard
{
    public function assertFeeCalculationReady(FeeCalculation $calculation): ?IntegrationScope
    {
        if ($calculation->method !== FeeMethod::Integration) {
            return null;
        }

        $scope = $calculation->integrationScope;
        if (! $scope instanceof IntegrationScope || (string) $scope->client_id !== (string) $calculation->client_id) {
            throw new InvalidArgumentException('Integration proposals require a client-owned integration scope.');
        }

        if (! $scope->isComplete()) {
            throw new InvalidArgumentException('Complete the integration scope before generating or releasing its proposal.');
        }

        $blocking = collect($scope->flags ?? [])
            ->contains(static fn (mixed $flag): bool => is_array($flag) && (bool) ($flag['blocking'] ?? false));
        if ($blocking) {
            throw new InvalidArgumentException('Resolve blocking integration scope flags before generating or releasing the proposal.');
        }

        return $scope;
    }

    public function assertProposalReady(Proposal $proposal): ?IntegrationScope
    {
        $proposal->loadMissing('feeCalculation.integrationScope');

        return $this->assertFeeCalculationReady($proposal->feeCalculation);
    }
}
