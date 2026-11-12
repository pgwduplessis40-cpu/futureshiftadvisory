<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

/**
 * The facts a rule reads, indexed for the way it reads them.
 *
 * Lookups were a full scan with a closure per fact, which is fine for a
 * handful and not for a reconstruction that states a scenario set: a
 * twenty-four month plan with three streams reaches two and a half thousand
 * facts, the gate makes a hundred and sixty-five distinct kinds of lookup
 * across its rules, and SS-01 alone took eleven seconds to evaluate one. The
 * gate runs inside an HTTP request, so that is not a slow test, it is a page
 * that does not load.
 *
 * Two indexes, both built once. `all()` reads by key, `one()` by the full
 * coordinate. Insertion order is preserved in both, because a rule that
 * iterates months or entries reports them in the order they were stated and
 * reordering would change its subcheck numbering.
 *
 * `one()` keeps its contract exactly: competing records under one coordinate
 * resolve to nothing rather than to the first of them. The index stores every
 * match rather than overwriting, so a duplicate is still visible as a
 * duplicate - overwriting would turn the ambiguity this is built to catch
 * into a silent last-one-wins.
 */
final readonly class FactSet implements \JsonSerializable
{
    /** @var array<string,list<Fact>> */
    private array $byKey;

    /** @var array<string,list<Fact>> */
    private array $byCoordinate;

    /** @param list<Fact> $facts */
    public function __construct(private array $facts = [])
    {
        $byKey = [];
        $byCoordinate = [];
        foreach ($facts as $fact) {
            $byKey[$fact->key][] = $fact;
            $byCoordinate[self::coordinate($fact->key, $fact->entity, $fact->period, $fact->scenario)][] = $fact;
        }
        $this->byKey = $byKey;
        $this->byCoordinate = $byCoordinate;
    }

    /** @return list<Fact> */
    public function all(?string $key = null, ?string $scenario = null): array
    {
        $facts = $key === null ? $this->facts : ($this->byKey[$key] ?? []);
        if ($scenario === null) {
            return $facts;
        }

        return array_values(array_filter($facts, static fn (Fact $fact): bool => $fact->scenario === $scenario));
    }

    public function one(string $key, string $entity = 'business', string $period = 'current', string $scenario = 'base'): ?Fact
    {
        $matches = $this->byCoordinate[self::coordinate($key, $entity, $period, $scenario)] ?? [];

        // Competing records must be resolved, never silently take the first.
        return count($matches) === 1 ? $matches[0] : null;
    }

    /** @return list<Fact> */
    public function jsonSerialize(): array
    {
        $facts = $this->facts;
        usort($facts, fn (Fact $a, Fact $b): int => strcmp(CanonicalJson::encode($a), CanonicalJson::encode($b)));

        return $facts;
    }

    /**
     * The four fields that identify a fact, joined by a separator none of them
     * can contain.
     *
     * A key, entity, period or scenario carrying the separator would let two
     * different coordinates collide into one and resolve each other to
     * nothing. A null byte cannot appear in any of them: they are identifiers
     * and period labels built by the producers, and every one is written as
     * printable text.
     */
    private static function coordinate(string $key, string $entity, string $period, string $scenario): string
    {
        return $key."\0".$entity."\0".$period."\0".$scenario;
    }
}
