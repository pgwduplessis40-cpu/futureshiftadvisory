<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Models\IntegrationCall;
use App\Services\Integration\IntegrationHealthIssue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class IntegrationHealthIssueTest extends TestCase
{
    #[DataProvider('failures')]
    public function test_maps_failures_to_specific_safe_actions(?int $status, string $message, string $code, string $action): void
    {
        $call = new IntegrationCall([
            'service' => 'anthropic',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'occurred_at' => now(),
            'error_payload' => ['http_status' => $status, 'message' => $message.' sk-ant-secret person@example.test', 'request_id' => 'req_support123'],
        ]);
        $issue = (new IntegrationHealthIssue)->forCall($call);

        $this->assertSame($code, $issue['code']);
        $this->assertStringContainsString($action, $issue['action']);
        $this->assertSame('req_support123', $issue['request_id']);
        $this->assertStringNotContainsString('sk-ant-secret', json_encode($issue, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('person@example.test', json_encode($issue, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{int|null,string,string,string}> */
    public static function failures(): array
    {
        return [
            'missing' => [null, 'admin_api_key_missing', 'credentials_missing', 'configure'],
            'authentication' => [401, '', 'authentication', 'API key'],
            'permission' => [403, '', 'permission', 'permissions'],
            'billing' => [402, '', 'billing', 'credit'],
            'credit restriction' => [400, 'Your credit balance is too low', 'billing', 'credit'],
            'tier spending cap' => [429, 'Monthly spend cap reached', 'billing', 'spending limits'],
            'rate limit' => [429, '', 'rate_limit', 'rate limit resets'],
            'overload' => [529, '', 'overloaded', 'service-status'],
            'timeout' => [null, 'cURL error 28: timed out', 'timeout', 'timeout settings'],
            'server error' => [500, '', 'provider_error', 'service-status'],
            'invalid request' => [400, '', 'request_error', 'request format'],
            'circuit' => [null, 'circuit_open', 'circuit_open', 'preceding failure'],
            'connection' => [null, 'admin_api_unreachable', 'connection', 'DNS'],
            'unknown' => [null, 'unrecognised', 'unknown', 'integration log'],
        ];
    }

    public function test_billing_credentials_and_untrusted_request_ids_are_handled_separately(): void
    {
        $issue = (new IntegrationHealthIssue)->forCall(new IntegrationCall([
            'service' => 'anthropic_admin',
            'endpoint' => 'https://api.anthropic.com/v1/organizations/cost_report',
            'error_payload' => ['http_status' => 401, 'request_id' => 'sk-ant-secret'],
        ]));
        $this->assertStringContainsString('Admin API key used for billing sync', $issue['action']);
        $this->assertNull($issue['request_id']);
    }

    public function test_provider_retry_delay_and_billing_eligibility_have_specific_guidance(): void
    {
        $mapper = new IntegrationHealthIssue;
        $rateLimit = $mapper->forCall(new IntegrationCall([
            'service' => 'anthropic', 'endpoint' => '/v1/messages',
            'error_payload' => ['http_status' => 429, 'retry_after_seconds' => 120],
        ]));
        $this->assertStringContainsString('Wait at least 120 seconds', $rateLimit['action']);

        $eligibility = $mapper->forCall(new IntegrationCall([
            'service' => 'anthropic_admin', 'endpoint' => '/v1/organizations/cost_report',
            'error_payload' => ['http_status' => 400, 'provider_message' => 'This organisation is not eligible for Usage and Cost API access.'],
        ]));
        $this->assertSame('billing_access', $eligibility['code']);
        $this->assertStringContainsString('Anthropic support', $eligibility['action']);
    }
}
