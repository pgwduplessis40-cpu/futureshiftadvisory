<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class BankOpening implements \JsonSerializable
{
    public function __construct(public ?string $analytical, public ?string $gstBridge, public ?string $bank,
        public ?string $restricted, public ?string $source, public ?bool $reviewed)
    {
        foreach ([$analytical, $gstBridge, $bank] as $amount) {
            AccountingNumbers::signed($amount);
        }
        DriverNumbers::read($restricted);
    }

    /** @return array{analytical:?string,gst_bridge:?string,bank:?string,restricted:?string,source:?string,reviewed:?bool} */
    public function jsonSerialize(): array
    {
        return ['analytical' => $this->analytical, 'gst_bridge' => $this->gstBridge, 'bank' => $this->bank,
            'restricted' => $this->restricted, 'source' => $this->source, 'reviewed' => $this->reviewed];
    }
}
