<?php

declare(strict_types=1);

namespace App\Support;

final class FspNumber
{
    public static function normalise(string $value): string
    {
        $normalised = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');

        if ($normalised !== '' && ctype_digit($normalised)) {
            return 'FSP'.$normalised;
        }

        return $normalised;
    }
}
