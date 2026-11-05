<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class CashOpening implements \JsonSerializable
{
    public function __construct(public ?string $amount, public ?string $source, public ?bool $verified)
    {
        DriverNumbers::read($amount !== null && str_starts_with($amount, '-') ? substr($amount, 1) : $amount);
    }

    /** @return array{amount:?string,source:?string,verified:?bool} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'source' => $this->source, 'verified' => $this->verified];
    }
}
