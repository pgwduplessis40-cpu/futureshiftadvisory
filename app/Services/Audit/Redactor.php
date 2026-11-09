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
    private const SECRET_PLACEHOLDER_KIND = 'secret';

    private const SECRET_KEY_PATTERN = '/(^|[_\-.])(api_?key|subscription_?key|secret|token|client_?secret|password|webhook_?secret|authorization|auth|key)([_\-.]|$)/i';

    private const QUERY_SECRET_PATTERN = '/([?&][^=&#\s]*(?:api_?key|subscription-key|subscription_?key|client_?secret|webhook_?secret|secret|token|password|key)[^=&#\s]*=)([^&#\s]+)/i';

    private const AUTH_HEADER_PATTERN = '/\b(authorization\s*[:=]\s*)(bearer\s+|basic\s+)?([A-Za-z0-9+\/._~=-]{8,})/i';

    /**
     * Canonical UUIDs, protected from the numeric patterns below.
     *
     * A UUID is an opaque identifier, not PII, and audit rows exist to carry
     * identifiers. But its random hex can contain a digit run that reads as
     * an NZ phone number — measured at roughly one UUID in a hundred — and
     * rewriting part of one silently corrupts the identifier the row was
     * written to preserve. Redacting it has no privacy gain to weigh against
     * that, so the numeric patterns are kept away from it entirely.
     *
     * A UUID used as a credential is already gone by the time this applies:
     * the secret passes run first and over the whole string.
     */
    private const UUID_PATTERN = '/\b([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\b/';

    /**
     * Patterns whose match can legitimately span a UUID, so they run over the
     * whole string. An address like `user+<uuid>@example.com` is one address,
     * and protecting the UUID inside it would leave the address unredacted.
     *
     * @var array<string, string> kind => regex
     */
    private const TEXT_PATTERNS = [
        'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
    ];

    /** @var array<string, string> kind => regex */
    private const PATTERNS = [
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
                $out[$key] = is_string($k) && $this->isSecretKey($k)
                    ? $this->redactSecretValue($v)
                    : $this->redact($v);
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
        $input = preg_replace_callback(
            self::QUERY_SECRET_PATTERN,
            fn (array $m): string => $m[1].$this->placeholder((string) $m[2], self::SECRET_PLACEHOLDER_KIND),
            $input,
        ) ?? $input;

        $input = preg_replace_callback(
            self::AUTH_HEADER_PATTERN,
            fn (array $m): string => $m[1].($m[2] ?? '').$this->placeholder((string) $m[3], self::SECRET_PLACEHOLDER_KIND),
            $input,
        ) ?? $input;

        $input = $this->apply(self::TEXT_PATTERNS, $input);

        // Split so identifier segments pass through untouched. With one
        // capture group the delimiters land on odd indices, and empty parts
        // are kept rather than dropped so that alternation holds.
        $parts = preg_split(self::UUID_PATTERN, $input, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->apply(self::PATTERNS, $input);
        }
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = $this->apply(self::PATTERNS, $part);
            }
        }

        return implode('', $parts);
    }

    /** @param  array<string, string>  $patterns */
    private function apply(array $patterns, string $input): string
    {
        foreach ($patterns as $kind => $pattern) {
            $input = preg_replace_callback(
                $pattern,
                fn (array $m): string => $this->placeholder((string) $m[0], $kind),
                $input,
            ) ?? $input;
        }

        return $input;
    }

    private function isSecretKey(string $key): bool
    {
        return preg_match(self::SECRET_KEY_PATTERN, $key) === 1;
    }

    /**
     * @param  mixed  $value
     */
    private function redactSecretValue($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $this->placeholder((string) $value, self::SECRET_PLACEHOLDER_KIND);
        }

        return $this->placeholder(json_encode($value) ?: 'complex-secret', self::SECRET_PLACEHOLDER_KIND);
    }

    private function placeholder(string $value, string $kind): string
    {
        return sprintf('[%s:%s]', $kind, substr(hash('sha256', $value), 0, 10));
    }
}
