<?php

declare(strict_types=1);

namespace App\Support\Methodology;

use InvalidArgumentException;

final readonly class MethodologyEntry
{
    /**
     * @param  array<int, string>  $inputs
     * @param  array<int, string>  $configRefs
     * @param  array<int, string>  $whereUsed
     * @param  array<int, string>  $sources
     */
    public function __construct(
        public string $id,
        public string $area,
        public string $name,
        public string $summary,
        public string $formula,
        public array $inputs,
        public array $configRefs,
        public array $whereUsed,
        public array $sources,
        public string $owningService,
        public string $version,
        public bool $internalOnly = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string|int $key, array $data): self
    {
        foreach (['id', 'area', 'name', 'summary', 'formula', 'inputs', 'config_refs', 'where_used', 'owning_service', 'version'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Methodology entry [{$key}] is missing [{$field}].");
            }
        }

        $id = self::stringField($key, $data, 'id');

        if (is_string($key) && $key !== $id) {
            throw new InvalidArgumentException("Methodology entry [{$key}] id [{$id}] must match its config key.");
        }

        $owningService = self::stringField($id, $data, 'owning_service');

        if (! class_exists($owningService)) {
            throw new InvalidArgumentException("Methodology entry [{$id}] owner [{$owningService}] does not exist.");
        }

        return new self(
            id: $id,
            area: self::stringField($id, $data, 'area'),
            name: self::stringField($id, $data, 'name'),
            summary: self::stringField($id, $data, 'summary'),
            formula: self::stringField($id, $data, 'formula'),
            inputs: self::stringList($id, $data, 'inputs'),
            configRefs: self::stringList($id, $data, 'config_refs'),
            whereUsed: self::stringList($id, $data, 'where_used'),
            sources: self::stringList($id, $data, 'sources', required: false),
            owningService: $owningService,
            version: self::stringField($id, $data, 'version'),
            internalOnly: self::boolField($id, $data, 'internal_only', default: true),
        );
    }

    /**
     * @return array{id:string, area:string, name:string, summary:string, formula:string, inputs:array<int,string>, config_refs:array<int,string>, where_used:array<int,string>, sources:array<int,string>, owning_service:string, version:string, internal_only:bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'area' => $this->area,
            'name' => $this->name,
            'summary' => $this->summary,
            'formula' => $this->formula,
            'inputs' => $this->inputs,
            'config_refs' => $this->configRefs,
            'where_used' => $this->whereUsed,
            'sources' => $this->sources,
            'owning_service' => $this->owningService,
            'version' => $this->version,
            'internal_only' => $this->internalOnly,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringField(string|int $entry, array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Methodology entry [{$entry}] field [{$field}] must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private static function stringList(string|int $entry, array $data, string $field, bool $required = true): array
    {
        if (! array_key_exists($field, $data)) {
            if (! $required) {
                return [];
            }

            throw new InvalidArgumentException("Methodology entry [{$entry}] is missing [{$field}].");
        }

        $value = $data[$field];

        if (! is_array($value)) {
            throw new InvalidArgumentException("Methodology entry [{$entry}] field [{$field}] must be a list of strings.");
        }

        $strings = [];

        foreach (array_values($value) as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("Methodology entry [{$entry}] field [{$field}] must be a list of non-empty strings.");
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function boolField(string|int $entry, array $data, string $field, bool $default): bool
    {
        if (! array_key_exists($field, $data)) {
            return $default;
        }

        if (! is_bool($data[$field])) {
            throw new InvalidArgumentException("Methodology entry [{$entry}] field [{$field}] must be boolean.");
        }

        return $data[$field];
    }
}
