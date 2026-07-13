<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentChargeLookup
{
    private const SUCCEEDED = 'succeeded';

    private const NOT_CHARGED = 'not_charged';

    private const UNKNOWN = 'unknown';

    private function __construct(public string $status, public ?PaymentChargeResult $charge = null) {}

    public static function succeeded(PaymentChargeResult $charge): self
    {
        return new self(self::SUCCEEDED, $charge);
    }

    public static function notCharged(): self
    {
        return new self(self::NOT_CHARGED);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::SUCCEEDED && $this->charge instanceof PaymentChargeResult;
    }

    public function isNotCharged(): bool
    {
        return $this->status === self::NOT_CHARGED;
    }
}
