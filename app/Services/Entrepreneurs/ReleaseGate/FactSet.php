<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class FactSet implements \JsonSerializable
{
    /** @param list<Fact> $facts */
    public function __construct(private array $facts = []) {}

    /** @return list<Fact> */
    public function all(?string $key = null, ?string $scenario = null): array
    {
        return array_values(array_filter($this->facts, fn (Fact $fact): bool => ($key === null || $fact->key === $key) && ($scenario === null || $fact->scenario === $scenario)));
    }

    public function one(string $key, string $entity = 'business', string $period = 'current', string $scenario = 'base'): ?Fact
    {
        $matches = array_values(array_filter($this->facts, fn (Fact $fact): bool => $fact->key === $key && $fact->entity === $entity && $fact->period === $period && $fact->scenario === $scenario));

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
}
