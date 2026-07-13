<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** The provider may have captured the charge but did not return a conclusive result. */
final class AmbiguousPaymentOutcome extends PaymentGatewayException {}
