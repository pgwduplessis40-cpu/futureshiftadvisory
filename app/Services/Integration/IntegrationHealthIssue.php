<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\IntegrationCall;

/**
 * @phpstan-type HealthIssue array{code:string,reason:string,action:string,http_status:int|null,request_id:string|null,occurred_at:string|null}
 */
final class IntegrationHealthIssue
{
    /**
     * Map diagnostics to fixed, safe guidance. Never return response bodies,
     * exception messages, endpoints, prompts, or credentials to this panel.
     *
     * @return HealthIssue
     */
    public function forCall(IntegrationCall $call): array
    {
        $payload = is_array($call->error_payload) ? $call->error_payload : [];
        $status = is_numeric($payload['http_status'] ?? null) ? (int) $payload['http_status'] : null;
        $text = strtolower(implode(' ', array_filter([
            $payload['reason'] ?? null, $payload['body'] ?? null,
            $payload['message'] ?? null, $payload['provider_message'] ?? null,
        ], 'is_string')));
        $billing = IntegrationHealthOperation::key($call) === 'anthropic_admin';
        $credential = $billing ? 'Anthropic Admin API key used for billing sync' : 'API key used by this integration';
        $retryAfter = $payload['retry_after_seconds'] ?? null;
        $retryWait = is_int($retryAfter) && $retryAfter >= 0 ? 'Wait at least '.$retryAfter.' seconds' : 'Wait until the provider rate limit resets';

        [$code, $reason, $action] = match (true) {
            str_contains($text, 'admin_api_key_missing') => ['credentials_missing', 'Billing credentials are missing.', 'Ask an administrator to configure the Anthropic Admin API key, then refresh billing sync.'],
            str_contains($text, 'invalid_admin_api_key'), $status === 401 => ['authentication', 'The provider rejected the credentials.', 'Ask an administrator to check or replace the '.$credential.', then retry the affected operation.'],
            $status === 403 => ['permission', 'The credential does not have permission for this operation.', 'Ask an administrator to check the key permissions and organisation access, then retry.'],
            $billing && str_contains($text, 'not eligible') => ['billing_access', 'The provider says this organisation is not eligible for the Usage and Cost API.', 'Ask your Anthropic organisation administrator to confirm Usage and Cost API access with Anthropic support, then recheck billing sync.'],
            $status === 402, str_contains($text, 'credit balance'), str_contains($text, 'billing_error'), str_contains($text, 'spend limit'), str_contains($text, 'spend cap') => ['billing', 'The provider reported a billing or spending-limit problem.', 'Check the provider console for available credit, payment details and spending limits. Resolve the reported restriction, then retry.'],
            $status === 429 => ['rate_limit', 'The provider rate limit was reached.', $retryWait.', then retry once. If this repeats, reduce concurrent requests or review the provider rate limits.'],
            $status === 529 => ['overloaded', 'The provider reported that it is overloaded.', 'Check the provider service-status page and retry after recovery. Escalate with the request ID if the problem persists.'],
            $status === 408, $status === 504, str_contains($text, 'timed out'), str_contains($text, 'timeout'), str_contains($text, 'curl error 28') => ['timeout', 'The request did not finish within its timeout.', 'Retry the affected operation once. If it repeats, ask the developer to inspect request size, processing time and timeout settings.'],
            str_contains($text, 'circuit_open') => ['circuit_open', 'Requests are temporarily paused after repeated failures.', 'Check the provider status and the preceding failure. Wait for the retry window, then retry the affected operation.'],
            $status !== null && $status >= 500 => ['provider_error', 'The provider returned a server error.', 'Check the provider service-status page, then retry. Give the developer the request ID if failures continue.'],
            $status === 400, $status === 404, $status === 422 => ['request_error', 'The provider rejected the request or requested resource.', 'Ask the developer to inspect the request format, model or endpoint configuration using the request ID.'],
            str_contains($text, 'exception'), str_contains($text, 'unreachable') => ['connection', 'The application could not reach the provider.', 'Ask the developer to check network access, DNS and TLS, then retry the affected operation.'],
            default => ['unknown', 'A retry, failed request or fallback was recorded.', 'Ask the developer to inspect the integration log for this time window, then retry the affected operation.'],
        };
        $requestId = $payload['request_id'] ?? null;

        return [
            'code' => $code,
            'reason' => $reason,
            'action' => $action,
            'http_status' => $status,
            'request_id' => is_string($requestId) && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $requestId) === 1 && ! str_starts_with($requestId, 'sk-') ? $requestId : null,
            'occurred_at' => $call->occurred_at?->toIso8601String(),
        ];
    }
}
