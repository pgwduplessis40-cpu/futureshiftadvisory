<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case CannotVerify = 'cannot_verify';
    case NotApplicable = 'not_applicable';

    public function unresolved(): bool
    {
        return $this === self::Fail || $this === self::CannotVerify;
    }
}
