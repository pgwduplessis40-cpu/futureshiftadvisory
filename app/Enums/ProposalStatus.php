<?php

declare(strict_types=1);

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Released = 'released';
    case Recalled = 'recalled';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case AwaitingSignature = 'awaiting_signature';
    case Signed = 'signed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, self>
     */
    public static function phaseTwoReachable(): array
    {
        return [
            self::Draft,
            self::Released,
            self::Recalled,
            self::Expired,
            self::Renewed,
        ];
    }

    public function phaseTwoReserved(): bool
    {
        return in_array($this, [self::AwaitingSignature, self::Signed], true);
    }
}
