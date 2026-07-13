<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\ClientTeamMember;
use App\Models\PaymentAuthority;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentGatewayFailureNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final class Gateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly WindcaveClient $windcave,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{currency?: string|null, idempotency_key?: string|null, metadata?: array<string, mixed>}  $options
     */
    public function charge(PaymentAuthority $authority, int|float|string $amount, array $options = [], ?User $actor = null): PaymentChargeResult
    {
        $authority = $authority->refresh();

        if ($authority->status !== PaymentAuthority::STATUS_ACTIVE || $authority->revoked_at !== null) {
            throw new InvalidArgumentException('Only active payment authorities can be charged.');
        }

        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];
        $this->assertNoRawPan($metadata);

        $amount = $this->normaliseAmount($amount);
        $currency = strtoupper((string) ($options['currency'] ?? 'NZD'));

        if ($currency !== 'NZD') {
            throw new InvalidArgumentException('Payment gateway charges currently support NZD only.');
        }

        $tokenPayload = $this->tokenPayload($authority);
        $primary = $this->preferredGateway();
        $secondary = $this->secondaryGateway($primary);
        $idempotencyKey = (string) ($options['idempotency_key'] ?? 'charge-'.$authority->getKey().'-'.Str::uuid());

        try {
            $result = $this->chargeGateway($primary, $authority, $tokenPayload, $amount, $currency, $idempotencyKey, $metadata);
            $this->auditSuccess($authority, $result, $actor);

            return $result;
        } catch (DefinitivePaymentDecline $primaryFailure) {
            $this->audit->record('payment_gateway.primary_failed', subject: $authority, actor: $actor, after: [
                'gateway' => $primary,
                'reason' => $primaryFailure->getMessage(),
            ]);

            try {
                $result = $this
                    ->chargeGateway($secondary, $authority, $tokenPayload, $amount, $currency, $idempotencyKey, $metadata)
                    ->withFailoverFrom($primary);
                $this->auditSuccess($authority, $result, $actor);
                $this->audit->record('payment_gateway.failover_succeeded', subject: $authority, actor: $actor, after: [
                    'failover_from' => $primary,
                    'gateway' => $result->gateway,
                    'gateway_ref' => $result->gatewayRef,
                ]);

                return $result;
            } catch (PaymentGatewayException $secondaryFailure) {
                $this->audit->record('payment_gateway.double_failure', subject: $authority, actor: $actor, after: [
                    'primary_gateway' => $primary,
                    'secondary_gateway' => $secondary,
                    'primary_reason' => $primaryFailure->getMessage(),
                    'secondary_reason' => $secondaryFailure->getMessage(),
                ]);

                $this->notifyDoubleFailure($authority, $primary, $secondary);

                throw new PaymentGatewayException('Both payment gateways failed for the charge attempt.', previous: $secondaryFailure);
            }
        } catch (PaymentGatewayException $ambiguousFailure) {
            $this->audit->record('payment_gateway.awaiting_confirmation', subject: $authority, actor: $actor, after: [
                'gateway' => $primary,
                'reason' => $ambiguousFailure->getMessage(),
            ]);

            throw $ambiguousFailure;
        }
    }

    public function findCharge(Payment $payment): PaymentChargeLookup
    {
        $payment->loadMissing('paymentInstallment');
        $gateway = $payment->gateway ?? $payment->paymentInstallment?->attempted_gateway;
        if (! in_array($gateway, PaymentAuthority::gateways(), true)) {
            return PaymentChargeLookup::unknown();
        }

        return $gateway === PaymentAuthority::GATEWAY_STRIPE
            ? $this->stripe->findCharge($payment->gateway_ref, (string) $payment->idempotency_key, (string) $payment->getKey())
            : $this->windcave->findCharge($payment->gateway_ref, (string) $payment->idempotency_key, (string) $payment->getKey());
    }

    /**
     * @param  array<string, mixed>  $tokenPayload
     * @param  array<string, mixed>  $metadata
     */
    private function chargeGateway(
        string $gateway,
        PaymentAuthority $authority,
        array $tokenPayload,
        string $amount,
        string $currency,
        string $idempotencyKey,
        array $metadata,
    ): PaymentChargeResult {
        $request = new PaymentChargeRequest(
            clientId: (string) $authority->client_id,
            proposalId: (string) $authority->proposal_id,
            authorityId: (string) $authority->getKey(),
            token: (string) ($tokenPayload['token'] ?? ''),
            customerRef: isset($tokenPayload['customer_ref']) ? (string) $tokenPayload['customer_ref'] : $authority->gateway_customer_ref,
            amount: $amount,
            currency: $currency,
            gateway: $gateway,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );

        return $gateway === PaymentAuthority::GATEWAY_STRIPE
            ? $this->stripe->charge($request)
            : $this->windcave->charge($request);
    }

    private function auditSuccess(PaymentAuthority $authority, PaymentChargeResult $result, ?User $actor): void
    {
        $this->audit->record('payment_gateway.charge_succeeded', subject: $authority, actor: $actor, after: [
            'gateway' => $result->gateway,
            'gateway_ref' => $result->gatewayRef,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'failover_from' => $result->failoverFrom,
        ]);
    }

    private function notifyDoubleFailure(PaymentAuthority $authority, string $primary, string $secondary): void
    {
        $recipients = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();

        $advisorIds = ClientTeamMember::query()
            ->where('client_id', $authority->client_id)
            ->whereIn('role', ['lead_advisor', 'advisor'])
            ->pluck('user_id');

        if ($advisorIds->isNotEmpty()) {
            $recipients = $recipients
                ->merge(User::query()->whereIn('id', $advisorIds)->get())
                ->unique(fn (User $user): int|string => $user->getKey())
                ->values();
        }

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new PaymentGatewayFailureNotification(
            authority: $authority,
            primaryGateway: $primary,
            secondaryGateway: $secondary,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(PaymentAuthority $authority): array
    {
        try {
            $decoded = json_decode($this->envelope->decrypt($authority->gateway_token_envelope), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Payment authority token could not be decoded.', previous: $e);
        }

        if (! is_array($decoded) || ! is_string($decoded['token'] ?? null) || $decoded['token'] === '') {
            throw new InvalidArgumentException('Payment authority token is missing.');
        }

        return $decoded;
    }

    public function preferredGateway(): string
    {
        $configured = (string) Config::get('integrations.payments.primary_gateway', PaymentAuthority::GATEWAY_STRIPE);

        return in_array($configured, PaymentAuthority::gateways(), true)
            ? $configured
            : PaymentAuthority::GATEWAY_STRIPE;
    }

    private function secondaryGateway(string $primary): string
    {
        return $primary === PaymentAuthority::GATEWAY_STRIPE
            ? PaymentAuthority::GATEWAY_WINDCAVE
            : PaymentAuthority::GATEWAY_STRIPE;
    }

    private function normaliseAmount(int|float|string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Payment charge amount must be numeric.');
        }

        $value = (float) $amount;

        if ($value <= 0) {
            throw new InvalidArgumentException('Payment charge amount must be greater than zero.');
        }

        return number_format($value, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoRawPan(array $payload): void
    {
        foreach (Arr::dot($payload) as $key => $value) {
            $key = strtolower((string) $key);

            if (str_contains($key, 'card_number') || $key === 'pan' || str_ends_with($key, '.pan')) {
                throw new InvalidArgumentException('Raw card numbers must not be submitted or stored.');
            }

            if (! is_scalar($value)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (strlen($digits) >= 13 && strlen($digits) <= 19 && str_contains($key, 'card')) {
                throw new InvalidArgumentException('Raw card numbers must not be submitted or stored.');
            }
        }
    }
}
