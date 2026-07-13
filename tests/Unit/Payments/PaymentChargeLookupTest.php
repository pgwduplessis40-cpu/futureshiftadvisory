<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Services\Integration\Stripe\FakeStripeClient;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\PaymentChargeRequest;
use PHPUnit\Framework\TestCase;

final class PaymentChargeLookupTest extends TestCase
{
    public function test_fixture_gateway_recovers_a_charge_by_its_durable_idempotency_key(): void
    {
        $gateway = new FakeStripeClient;
        $request = new PaymentChargeRequest(
            clientId: 'client-1',
            proposalId: 'proposal-1',
            authorityId: 'authority-1',
            token: 'token',
            customerRef: 'customer-1',
            amount: '115.00',
            currency: 'NZD',
            gateway: 'stripe',
            idempotencyKey: 'installment-1-attempt-1',
            metadata: ['payment_id' => 'payment-1'],
        );

        $charge = $gateway->charge($request);
        $lookup = $gateway->findCharge($charge->gatewayRef, $request->idempotencyKey, 'payment-1');

        $this->assertTrue($lookup->isSucceeded());
        $this->assertSame($charge->gatewayRef, $lookup->charge?->gatewayRef);
        $this->assertFalse($lookup->isNotCharged());
    }

    public function test_fixture_failure_is_a_definitive_decline(): void
    {
        $this->expectException(DefinitivePaymentDecline::class);

        (new FakeStripeClient)->charge(new PaymentChargeRequest(
            clientId: 'client-1',
            proposalId: 'proposal-1',
            authorityId: 'authority-1',
            token: 'token',
            customerRef: 'customer-1',
            amount: '115.00',
            currency: 'NZD',
            gateway: 'stripe',
            idempotencyKey: 'installment-1-attempt-1',
            metadata: ['fixture_fail' => true],
        ));
    }
}
