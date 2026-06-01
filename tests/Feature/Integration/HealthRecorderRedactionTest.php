<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\IntegrationCall;
use App\Services\Integration\Resilience\HealthRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthRecorderRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_recorder_redacts_endpoint_query_secrets_and_error_payload_auth(): void
    {
        app(HealthRecorder::class)->record(
            service: 'stats-nz',
            endpoint: 'https://example.test/data?subscription-key=secret-subscription-key&resource=abc',
            status: IntegrationCall::STATUS_FAILURE,
            attempt: 1,
            errorPayload: [
                'headers' => [
                    'Authorization' => 'Bearer upstream-secret-token',
                ],
                'api_key' => 'echoed-api-key',
            ],
        );

        $call = IntegrationCall::query()->firstOrFail();
        $encoded = json_encode($call->error_payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('secret-subscription-key', $call->endpoint);
        $this->assertStringNotContainsString('upstream-secret-token', $encoded);
        $this->assertStringNotContainsString('echoed-api-key', $encoded);
        $this->assertStringContainsString('[secret:', $call->endpoint);
        $this->assertStringContainsString('[secret:', $encoded);
    }
}
