<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditWriter;
use App\Services\Payments\PaymentWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookVerifier $verifier,
        private readonly AuditWriter $audit,
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        return $this->handle(
            gateway: 'stripe',
            request: $request,
            verifier: fn (): array => $this->verifier->verifyStripe($request),
        );
    }

    public function windcave(Request $request): JsonResponse
    {
        return $this->handle(
            gateway: 'windcave',
            request: $request,
            verifier: fn (): array => $this->verifier->verifyWindcave($request),
        );
    }

    /**
     * @param  callable(): array{0: bool, 1: string|null}  $verifier
     */
    private function handle(string $gateway, Request $request, callable $verifier): JsonResponse
    {
        [$valid, $reason] = $verifier();

        if (! $valid) {
            $this->audit->record('payment.webhook_rejected', after: [
                'gateway' => $gateway,
                'reason' => $reason,
                'payload_hash' => hash('sha256', $request->getContent()),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();

        $this->audit->record('payment.webhook_received', after: [
            'gateway' => $gateway,
            'event_id' => is_scalar($payload['id'] ?? null) ? (string) $payload['id'] : null,
            'event_type' => is_scalar($payload['type'] ?? null) ? (string) $payload['type'] : null,
            'payload_hash' => hash('sha256', $request->getContent()),
        ]);

        return response()->json(['received' => true], 202);
    }
}
