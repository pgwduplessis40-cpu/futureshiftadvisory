<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use Normalizer;

final class PdfText
{
    public static function normalise(string $body): string
    {
        // Styled Unicode letters are text, not font styling. Use ordinary letters
        // so headings remain readable in PDF fonts without losing their wording.
        $body = preg_replace_callback('/[\x{1D400}-\x{1D7FF}]+/u',
            fn (array $match): string => Normalizer::normalize($match[0], Normalizer::FORM_KC) ?: $match[0],
            $body,
        ) ?? $body;
        $body = preg_replace(
            '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\p{Cf}\p{Co}\p{Cs}\p{Cn}\x{FFFD}\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}]/u',
            '',
            $body,
        ) ?? $body;

        return preg_replace('/(?m)^(\s*(?:[-*]\s*)?)(?:[\p{So},.;:!?]+\s*)+/u', '$1', $body) ?? $body;
    }
}
