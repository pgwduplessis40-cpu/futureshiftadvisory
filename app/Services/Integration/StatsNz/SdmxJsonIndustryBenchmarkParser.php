<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use Illuminate\Support\Str;

final class SdmxJsonIndustryBenchmarkParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $dataset
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $payload, array $dataset): array
    {
        $records = [];
        $seriesDimensions = $this->dimensions(data_get($payload, 'structure.dimensions.series', []));
        $observationDimensions = $this->dimensions(data_get($payload, 'structure.dimensions.observation', []));
        $dataSets = data_get($payload, 'dataSets', []);

        if (! is_array($dataSets)) {
            return [];
        }

        foreach ($dataSets as $dataSet) {
            if (! is_array($dataSet)) {
                continue;
            }

            $records = [
                ...$records,
                ...$this->parseFlatObservations((array) ($dataSet['observations'] ?? []), $observationDimensions, $dataset),
                ...$this->parseSeries((array) ($dataSet['series'] ?? []), $seriesDimensions, $observationDimensions, $dataset),
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $observations
     * @param  array<int, array<string, mixed>>  $dimensions
     * @param  array<string, mixed>  $dataset
     * @return array<int, array<string, mixed>>
     */
    private function parseFlatObservations(array $observations, array $dimensions, array $dataset): array
    {
        $records = [];

        foreach ($observations as $observationKey => $observation) {
            $value = $this->observationValue($observation);
            if ($value === null) {
                continue;
            }

            $record = $this->record(
                context: $this->dimensionValues($dimensions, (string) $observationKey),
                dataset: $dataset,
                value: $value,
                seriesKey: null,
                observationKey: (string) $observationKey,
            );

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $series
     * @param  array<int, array<string, mixed>>  $seriesDimensions
     * @param  array<int, array<string, mixed>>  $observationDimensions
     * @param  array<string, mixed>  $dataset
     * @return array<int, array<string, mixed>>
     */
    private function parseSeries(array $series, array $seriesDimensions, array $observationDimensions, array $dataset): array
    {
        $records = [];

        foreach ($series as $seriesKey => $seriesPayload) {
            if (! is_array($seriesPayload)) {
                continue;
            }

            $seriesContext = $this->dimensionValues($seriesDimensions, (string) $seriesKey);
            $observations = (array) ($seriesPayload['observations'] ?? []);

            foreach ($observations as $observationKey => $observation) {
                $value = $this->observationValue($observation);
                if ($value === null) {
                    continue;
                }

                $record = $this->record(
                    context: [...$seriesContext, ...$this->dimensionValues($observationDimensions, (string) $observationKey)],
                    dataset: $dataset,
                    value: $value,
                    seriesKey: (string) $seriesKey,
                    observationKey: (string) $observationKey,
                );

                if ($record !== null) {
                    $records[] = $record;
                }
            }
        }

        return $records;
    }

    /**
     * @param  array<int|string, mixed>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    private function dimensions(array $dimensions): array
    {
        return array_values(array_filter($dimensions, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $dimensions
     * @return array<string, array{id:string, label:string, position:int}>
     */
    private function dimensionValues(array $dimensions, string $key): array
    {
        $indexes = $key === '' ? [] : explode(':', $key);
        $context = [];

        foreach ($dimensions as $position => $dimension) {
            $id = (string) ($dimension['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $index = (int) ($indexes[$position] ?? 0);
            $values = is_array($dimension['values'] ?? null) ? $dimension['values'] : [];
            $value = is_array($values[$index] ?? null) ? $values[$index] : [];
            $code = (string) ($value['id'] ?? $value['code'] ?? ($indexes[$position] ?? ''));
            $label = (string) ($value['name'] ?? $value['label'] ?? $code);

            $context[$id] = [
                'id' => $code,
                'label' => $label,
                'position' => $position,
            ];
        }

        return $context;
    }

    /**
     * @param  array<string, array{id:string, label:string, position:int}>  $context
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>|null
     */
    private function record(array $context, array $dataset, float $value, ?string $seriesKey, string $observationKey): ?array
    {
        $industry = $this->dimension($context, (string) ($dataset['dimension_key'] ?? 'INDUSTRY'), ['industry', 'anzsic']);
        if ($industry === null) {
            return null;
        }

        $metric = $this->dimension($context, (string) ($dataset['metric_dimension_key'] ?? 'MEASURE'), ['measure', 'metric', 'variable']);
        $period = $this->dimension($context, (string) ($dataset['time_dimension_key'] ?? 'TIME_PERIOD'), ['time', 'period', 'year']);
        $metricKey = $this->slug((string) ($metric['id'] ?? $metric['label'] ?? $dataset['metric'] ?? 'value'));

        return [
            'dataset_key' => (string) ($dataset['key'] ?? 'stats_nz_dataset'),
            'dataset_label' => (string) ($dataset['label'] ?? 'Stats NZ dataset'),
            'resource_id' => (string) ($dataset['resourceId'] ?? ''),
            'version' => (string) ($dataset['version'] ?? '1.0'),
            'industry_code' => (string) $industry['id'],
            'industry_label' => (string) $industry['label'],
            'metric' => $metricKey,
            'metric_label' => (string) ($metric['label'] ?? Str::headline($metricKey)),
            'period' => (string) ($period['id'] ?? ''),
            'period_label' => (string) ($period['label'] ?? $period['id'] ?? ''),
            'value' => round($value, 6),
            'unit' => (string) ($dataset['unit'] ?? 'value'),
            'source' => 'stats_nz',
            'payload' => [
                'dimensions' => $context,
                'series_key' => $seriesKey,
                'observation_key' => $observationKey,
                'dimension_at_observation' => (string) ($dataset['dimensionAtObservation'] ?? 'AllDimensions'),
            ],
        ];
    }

    /**
     * @param  array<string, array{id:string, label:string, position:int}>  $context
     * @param  array<int, string>  $fallbackNeedles
     * @return array{id:string, label:string, position:int}|null
     */
    private function dimension(array $context, string $preferred, array $fallbackNeedles): ?array
    {
        if (isset($context[$preferred])) {
            return $context[$preferred];
        }

        foreach ($context as $id => $value) {
            $needle = Str::lower($id.' '.$value['label']);
            foreach ($fallbackNeedles as $fallbackNeedle) {
                if (str_contains($needle, $fallbackNeedle)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function observationValue(mixed $observation): ?float
    {
        if (! is_array($observation)) {
            return is_numeric($observation) ? (float) $observation : null;
        }

        $value = array_is_list($observation)
            ? ($observation[0] ?? null)
            : ($observation['value'] ?? $observation['OBS_VALUE'] ?? null);

        return is_numeric($value) ? (float) $value : null;
    }

    private function slug(string $value): string
    {
        $slug = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $slug === '' ? 'value' : $slug;
    }
}
