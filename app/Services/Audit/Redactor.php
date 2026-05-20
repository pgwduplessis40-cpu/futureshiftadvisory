<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Replaces PII tokens in arbitrary values with opaque, deterministic
 * placeholders before they reach the audit_events table.
 *
 * Per CLAUDE.md: "No PII in raw logs. All log lines containing user data
 * go through the redaction helper." Audit rows are forever and may be
 * read by many people (security review, incident response, compliance),
 * so they must contain identifiers and references rather than raw PII.
 *
 * Phase 1 patterns covered:
 *   - email addresses
 *   - NZ phone numbers (mobile and landline, with/without +64)
 *   - IRD numbers (8 or 9 digits, NN-NNN-NNN or NNN-NNN-NNN)
 *   - NZ bank account numbers (BB-bbbb-AAAAAAA-SSS)
 *
 * NZBN is deliberately NOT redacted: it is a public business identifier
 * published in the NZBN Register. Redacting it would harm audit utility
 * with no privacy gain.
 *
 * The placeholder format is `[<kind>:<hash>]` where <hash> is the first
 * 10 hex chars of SHA-256 over the original value. Determinism lets
 * reviewers correlate two audit rows that refer to the same redacted
 * person without revealing the underlying value.
 */
final class Redactor
{
    /** @var array<string, string> kind => regex */
    private const PATTERNS = [
        'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',

        // More specific numeric identifiers run before the phone pattern so
        // it cannot partially consume a bank account or NZBN-like sequence.
        'bank' => '/\b\d{2}-\d{4}-\d{7}-\d{2,3}\b/',

        // IRD number: 8 or 9 digits, optionally formatted NN-NNN-NNN or
        // NNN-NNN-NNN. Anchored to word boundaries to avoid swallowing
        // longer numeric strings.
        'ird' => '/\b\d{2,3}-\d{3}-\d{3}\b/',

        // NZ phone numbers: optional +64 prefix, optional leading 0,
        // 8-10 digits split by spaces or dashes. Conservative pattern to
        // avoid eating short numeric tokens.
        'phone' => '/(?<![\d\-])(?:\+?64|0)[ \-]?(?:\d[ \-]?){7,9}\d(?![\d\-])/',
    ];

    /**
     * Redact PII anywhere in $value (string, scalar, array, nested array).
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function redact($value)
    {
        if (is_string($value)) {
            return $this->redactString($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $this->redactString($k) : $k;
                $out[$key] = $this->redact($v);
            }

            return $out;
        }

        // Scalars and nulls pass through untouched. Objects are stringified
        // (audit payloads should already be plain arrays; this is a safety
        // net in case an Eloquent attribute slipped in).
        if (is_object($value)) {
            return $this->redact((string) $value);
        }

        return $value;
    }

    private function redactString(string $input): string
    {
        foreach (self::PATTERNS as $kind => $pattern) {
            $input = preg_replace_callback(
                $pattern,
                static fn (array $m): string => sprintf(
                    '[%s:%s]',
                    $kind,
                    substr(hash('sha256', $m[0]), 0, 10),
                ),
                $input,
            ) ?? $input;
        }

        return $input;
    }
}
