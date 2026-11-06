<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

/**
 * Typed reads out of decoded forecast JSON.
 *
 * Every accessor takes the raw node as `mixed` and states the type it expects,
 * so a stored payload is never carried around as an untyped bag. Two rules
 * matter more than the mechanics:
 *
 * A key that is absent or null reads as null and stays null. It must never
 * become zero, because a missing driver and a driver the founder set to zero
 * mean different things to every rule downstream.
 *
 * A key that is present with the wrong type throws. A stored model that cannot
 * be read back is a load failure the advisor must see, not a reconstruction
 * that quietly comes back emptier than it was saved.
 */
final class StoredValues
{
    /** A nullable string: absent, null, or a string. Anything else is a load failure. */
    public static function text(mixed $node, string $key): ?string
    {
        $value = self::child($node, $key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is not text.', $key));
        }

        return $value;
    }

    /** A required string. Absent, null or empty is a load failure. */
    public static function requiredText(mixed $node, string $key): string
    {
        $value = self::text($node, $key);
        if ($value === null || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is missing.', $key));
        }

        return $value;
    }

    public static function flag(mixed $node, string $key): ?bool
    {
        $value = self::child($node, $key);
        if ($value === null) {
            return null;
        }
        if (! is_bool($value)) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is not a true/false flag.', $key));
        }

        return $value;
    }

    public static function count(mixed $node, string $key): ?int
    {
        $value = self::child($node, $key);
        if ($value === null) {
            return null;
        }
        if (! is_int($value)) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is not a whole number.', $key));
        }

        return $value;
    }

    public static function requiredCount(mixed $node, string $key): int
    {
        $value = self::count($node, $key);
        if ($value === null) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is missing.', $key));
        }

        return $value;
    }

    /**
     * The members of a stored JSON array, as raw nodes for a typed decoder.
     *
     * @return list<mixed>
     */
    public static function items(mixed $node, string $key): array
    {
        $value = self::child($node, $key);
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is not a list.', $key));
        }

        return $value;
    }

    /**
     * A list of plain strings, for the few places that store bare identifiers.
     *
     * @return list<string>
     */
    public static function textItems(mixed $node, string $key): array
    {
        $items = [];
        foreach (self::items($node, $key) as $item) {
            if (! is_string($item)) {
                throw new \InvalidArgumentException(sprintf('Stored forecast list "%s" holds a value that is not text.', $key));
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * The keys of a stored JSON object, so a keyed collection can be rebuilt
     * one typed member at a time.
     *
     * @return list<string>
     */
    public static function keys(mixed $node, string $key): array
    {
        $value = self::child($node, $key);
        if ($value === null) {
            return [];
        }
        // An empty keyed collection encodes as [], which is also an empty
        // list. Rejecting it would make a model with no founder months
        // unloadable, so emptiness is read as no keys rather than as the
        // wrong type.
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException(sprintf('Stored forecast value "%s" is not a keyed object.', $key));
        }

        $keys = [];
        foreach (array_keys($value) as $member) {
            $keys[] = (string) $member;
        }

        return $keys;
    }

    /** True when the key is present and not null, which distinguishes an
     * omitted nested object from one that was stored empty.
     */
    public static function has(mixed $node, string $key): bool
    {
        return self::child($node, $key) !== null;
    }

    /** One raw sub-node, for a decoder that knows what shape to expect. */
    public static function child(mixed $node, string|int $key): mixed
    {
        if (! is_array($node)) {
            return null;
        }

        return $node[$key] ?? null;
    }
}
