<?php

declare(strict_types=1);

namespace App\Support\Methodology;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class MethodologyRegistry
{
    public const ID_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * @return array<string, MethodologyEntry>
     */
    public function all(): array
    {
        $rawEntries = Config::get('methodologies.entries', []);

        if (! is_array($rawEntries)) {
            throw new InvalidArgumentException('Methodology entries must be an array.');
        }

        $entries = [];

        foreach ($rawEntries as $key => $rawEntry) {
            if (! is_array($rawEntry)) {
                throw new InvalidArgumentException("Methodology entry [{$key}] must be an array.");
            }

            $entry = MethodologyEntry::fromArray($key, $rawEntry);
            $this->assertValidId($entry->id);

            if (isset($entries[$entry->id])) {
                throw new InvalidArgumentException("Duplicate methodology id [{$entry->id}].");
            }

            $entries[$entry->id] = $entry;
        }

        ksort($entries);

        return $entries;
    }

    public function get(string $id): MethodologyEntry
    {
        $this->assertValidId($id);

        $entries = $this->all();

        if (! isset($entries[$id])) {
            throw new InvalidArgumentException("Methodology [{$id}] is not registered.");
        }

        return $entries[$id];
    }

    /**
     * @return array<string, MethodologyEntry>
     */
    public function byArea(string $area): array
    {
        return array_filter(
            $this->all(),
            static fn (MethodologyEntry $entry): bool => strcasecmp($entry->area, $area) === 0,
        );
    }

    /**
     * @return array<string, MethodologyEntry>
     */
    public function byFeature(string $featureKey): array
    {
        return array_filter(
            $this->all(),
            static fn (MethodologyEntry $entry): bool => in_array($featureKey, $entry->whereUsed, true),
        );
    }

    public function featureLabel(string $featureKey): string
    {
        $labels = Config::get('methodologies.feature_labels', []);

        if (! is_array($labels) || ! isset($labels[$featureKey]) || ! is_string($labels[$featureKey])) {
            throw new InvalidArgumentException("Methodology feature key [{$featureKey}] is not registered.");
        }

        return $labels[$featureKey];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedParameters(MethodologyEntry|string $entry): array
    {
        $entry = is_string($entry) ? $this->get($entry) : $entry;
        $parameters = [];

        foreach ($entry->configRefs as $configRef) {
            $this->assertAllowedConfigRef($configRef);

            if (! $this->configPathExists($configRef)) {
                throw new InvalidArgumentException("Methodology config_ref [{$configRef}] does not exist.");
            }

            $parameters[$configRef] = Config::get($configRef);
        }

        return $parameters;
    }

    private function assertValidId(string $id): void
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException("Methodology id [{$id}] must be a lowercase dotted slug.");
        }
    }

    private function assertAllowedConfigRef(string $configRef): void
    {
        $lowerConfigRef = Str::lower($configRef);

        foreach ($this->sensitiveConfigRefPatterns() as $pattern) {
            if (Str::is(Str::lower($pattern), $lowerConfigRef)) {
                throw new InvalidArgumentException("Methodology config_ref [{$configRef}] matches a sensitive pattern.");
            }
        }

        foreach ($this->allowedConfigRefPatterns() as $pattern) {
            if (Str::is($pattern, $configRef)) {
                return;
            }
        }

        throw new InvalidArgumentException("Methodology config_ref [{$configRef}] is not allowlisted.");
    }

    /**
     * @return array<int, string>
     */
    private function allowedConfigRefPatterns(): array
    {
        $patterns = Config::get('methodologies.config_ref_allowlist', []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveConfigRefPatterns(): array
    {
        $patterns = Config::get('methodologies.config_ref_sensitive_patterns', []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }

    private function configPathExists(string $configRef): bool
    {
        $segments = explode('.', $configRef);
        $value = Config::get(array_shift($segments));

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }
}
