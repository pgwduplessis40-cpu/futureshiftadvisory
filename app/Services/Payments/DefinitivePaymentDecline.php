<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** A gateway has confirmed that this charge was not captured. */
final class DefinitivePaymentDecline extends PaymentGatewayException {}
