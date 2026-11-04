<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class DisplayPolicy
{
    private function __construct(private int $decimals) {}

    public static function from(?Fact $fact): ?self
    {
        return match ($fact?->value) {
            'NZD; whole dollars; day month year' => new self(0),
            'NZD; two decimals; day month year' => new self(2),
            default => null,
        };
    }

    public function accepts(string $text): bool
    {
        preg_match_all('/(?<currency>NZ\$|NZD|USD|AUD|EUR|GBP|\$|€|£)\s*-?(?<amount>\d[\d,]*(?:\.\d+)?)/u', $text, $amounts, PREG_SET_ORDER);
        foreach ($amounts as $amount) {
            if (! in_array($amount['currency'], ['NZ$', 'NZD', '$'], true)
                || preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.(\d+))?$/D', $amount['amount'], $parts) !== 1
                || strlen($parts[1] ?? '') !== $this->decimals) {
                return false;
            }
        }
        // A shared day-month-name-year policy rules out ambiguous numeric dates.
        if (preg_match('/\b(?:\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4})\b/', $text) === 1) {
            return false;
        }
        preg_match_all('/\b(\d{1,2})\s+(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{4})\b/i', $text, $dates, PREG_SET_ORDER);
        foreach ($dates as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!j M Y', $date[1].' '.substr($date[2], 0, 3).' '.$date[3]);
            if ($parsed === false || $parsed->format('j') !== ltrim($date[1], '0')) {
                return false;
            }
        }

        return true;
    }
}
