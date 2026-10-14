<?php

declare(strict_types=1);

namespace App\Services\CoBrowse;

final class CoBrowseTargetRegistry
{
    /**
     * @return array<string, string>
     */
    public function targetsFor(string $routeKey): array
    {
        return match ($routeKey) {
            'portal.dashboard' => [
                'client.dashboard.workspace' => 'Dashboard workspace',
                'client.dashboard.progress' => 'Progress',
            ],
            'portal.entrepreneur.dashboard' => [
                'entrepreneur.dashboard.journey' => 'Journey',
                'entrepreneur.dashboard.progress' => 'Plan completion',
            ],
            'portal.entrepreneur.plan' => [
                'entrepreneur.plan.steps' => 'Plan steps',
                'entrepreneur.plan.budget' => 'Budget editor',
                'entrepreneur.plan.fixed-costs' => 'Add another cost',
                'entrepreneur.plan.forecast-start-month' => 'Forecast start month',
                'entrepreneur.plan.revenue-forecast' => 'Sales forecast',
                'entrepreneur.plan.funding-sources' => 'Funding and cash',
            ],
            default => [],
        };
    }

    public function assertKnown(string $routeKey, string $target): void
    {
        abort_unless(array_key_exists($target, $this->targetsFor($routeKey)), 422, 'That guidance target is unavailable on this page.');
    }
}
