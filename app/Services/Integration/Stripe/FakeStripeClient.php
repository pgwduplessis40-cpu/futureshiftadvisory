<?php

declare(strict_types=1);

namespace App\Services\Integration\Stripe;

use App\Services\Integration\Stripe\Contracts\StripeClient;

final class FakeStripeClient implements StripeClient {}
