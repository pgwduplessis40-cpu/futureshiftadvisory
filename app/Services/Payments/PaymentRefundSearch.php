<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentRefundSearch
{
    private const FOUND = 'found';

    private const UNKNOWN = 'unknown';

    /**
     * @param  list<PaymentRefundResult>  $refunds
     */
    private function __construct(public string $status, public array $refunds = []) {}

    /**
     * @param  list<PaymentRefundResult>  $refunds
     */
    public static function found(array $refunds): self
    {
        return new self(self::FOUND, $refunds);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }
}
