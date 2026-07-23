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
            default => [],
        };
    }

    public function assertKnown(string $routeKey, string $target): void
    {
        abort_unless(array_key_exists($target, $this->targetsFor($routeKey)), 422, 'That guidance target is unavailable on this page.');
    }
}
