<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentRefundLookup
{
    private const SUCCEEDED = 'succeeded';

    private const NOT_REFUNDED = 'not_refunded';

    private const UNKNOWN = 'unknown';

    private function __construct(public string $status, public ?PaymentRefundResult $refund = null) {}

    public static function succeeded(PaymentRefundResult $refund): self
    {
        return new self(self::SUCCEEDED, $refund);
    }

    public static function notRefunded(): self
    {
        return new self(self::NOT_REFUNDED);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::SUCCEEDED && $this->refund instanceof PaymentRefundResult;
    }
}
