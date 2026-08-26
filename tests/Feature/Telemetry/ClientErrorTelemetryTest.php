<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientErrorTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_only_the_pii_safe_client_error_contract(): void
    {
        $payload = [
            'release_sha' => 'abc1234',
            'route' => '/portal/entrepreneur',
            'feature' => 'react.error_boundary',
            'browser' => [
                'family' => 'chrome',
                'platform' => 'Win32',
                'viewport' => '1440x900',
            ],
            'error_fingerprint' => 'f00dbabe',
            'sanitized_stack' => 'TypeError: [message-redacted]',
        ];

        $this->postJson('/api/telemetry/client-errors', $payload)->assertNoContent();

        $this->postJson('/api/telemetry/client-errors', [
            ...$payload,
            'email' => 'client@example.test',
        ])->assertUnprocessable();

        $this->postJson('/api/telemetry/client-errors', [
            ...$payload,
            'sanitized_stack' => 'Error: token=do-not-send',
        ])->assertUnprocessable();
    }
}
