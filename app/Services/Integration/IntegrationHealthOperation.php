<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationCall;

final class IntegrationHealthOperation
{
    public static function key(IntegrationCall $call): string
    {
        // Classify older billing probes without rewriting the original call log.
        if ($call->service === 'anthropic' && parse_url($call->endpoint, PHP_URL_PATH) === '/v1/organizations/cost_report') {
            return 'anthropic_admin';
        }

        return $call->service;
    }

    public static function label(string $service): string
    {
        return match ($service) {
            'anthropic' => 'Anthropic AI requests',
            'anthropic_admin' => 'Anthropic billing sync',
            default => $service,
        };
    }
}
