<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class SourceRecords
{
    /** @var array<array-key,mixed> */
    private array $records;

    public function __construct(public SourceVersion $version)
    {
        $decoded = json_decode($version->sourceJson, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Source capture must contain records.');
        }
        $this->records = $decoded;
    }

    public function value(string $path): mixed
    {
        return data_get($this->records, $path);
    }

    /** @return list<array<array-key,mixed>> */
    public function rows(string $path): array
    {
        $value = $this->value($path);

        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    public function reference(string $path): SourceReference
    {
        return new SourceReference('captured_record', $this->version->planId, $path, CanonicalJson::hash($this->value($path)));
    }

    public function fact(string $key, string $path, string $entity = 'business', string $period = 'current', string $scenario = 'base', ?string $unit = null, ?string $basis = null, ?string $asOf = null, bool $confirmed = false): Fact
    {
        $value = $this->value($path);

        return new Fact($key, is_float($value) ? (string) $value : (is_scalar($value) ? $value : null),
            $entity, $period, $scenario, $unit, $basis, $asOf, $confirmed, [$this->reference($path)]);
    }
}
