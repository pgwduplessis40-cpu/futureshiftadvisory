<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Models\NpoEngagement;
use App\Models\NpoImpactMetric;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class NpoImpactMetricRecorder
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(NpoEngagement $engagement, array $input, ?User $actor = null): NpoImpactMetric
    {
        $key = $this->metricKey($input);
        $label = trim((string) ($input['metric_label'] ?? str($key)->replace('_', ' ')->title()));
        $value = $this->nonNegativeNumber($input['value'] ?? null, 'Impact metric value');
        $platformValue = array_key_exists('platform_value', $input) && $input['platform_value'] !== null && $input['platform_value'] !== ''
            ? $this->nonNegativeNumber($input['platform_value'], 'Platform metric value')
            : null;

        if ($platformValue !== null && $value > $platformValue) {
            throw new InvalidArgumentException('Impact metric value exceeds recorded platform data.');
        }

        return DB::transaction(function () use ($engagement, $key, $label, $value, $platformValue, $input, $actor): NpoImpactMetric {
            /** @var NpoImpactMetric $metric */
            $metric = NpoImpactMetric::query()->create([
                'client_id' => $engagement->client_id,
                'npo_engagement_id' => $engagement->getKey(),
                'metric_key' => $key,
                'metric_label' => $label,
                'value' => $value,
                'unit' => $this->nullableString($input['unit'] ?? null),
                'platform_value' => $platformValue,
                'period_start' => $input['period_start'] ?? null,
                'period_end' => $input['period_end'] ?? null,
                'source' => (string) ($input['source'] ?? 'client_portal'),
                'notes' => $this->nullableString($input['notes'] ?? null),
                'entered_by_user_id' => $actor?->getKey(),
            ]);

            $this->audit->record('npo.impact_metric.recorded', subject: $metric, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'metric_key' => $metric->metric_key,
                'value' => $metric->value,
                'platform_value' => $metric->platform_value,
            ]);

            return $metric->refresh();
        });
    }

    /**
     * @return EloquentCollection<int, NpoImpactMetric>
     */
    public function latest(NpoEngagement $engagement, int $limit = 8): EloquentCollection
    {
        return NpoImpactMetric::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function reportMetrics(NpoEngagement $engagement): array
    {
        $metrics = NpoImpactMetric::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->get()
            ->unique('metric_key')
            ->values();

        return [
            'metrics' => $metrics
                ->mapWithKeys(fn (NpoImpactMetric $metric): array => [
                    $metric->metric_key => $metric->value,
                ])
                ->all(),
            'platform_metrics' => $metrics
                ->filter(fn (NpoImpactMetric $metric): bool => $metric->platform_value !== null)
                ->mapWithKeys(fn (NpoImpactMetric $metric): array => [
                    $metric->metric_key => $metric->platform_value,
                ])
                ->all(),
            'payload' => $metrics
                ->map(fn (NpoImpactMetric $metric): array => $this->payload($metric))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(NpoImpactMetric $metric): array
    {
        return [
            'id' => $metric->id,
            'metric_key' => $metric->metric_key,
            'metric_label' => $metric->metric_label,
            'value' => $metric->value,
            'unit' => $metric->unit,
            'platform_value' => $metric->platform_value,
            'period_start' => $metric->period_start?->toDateString(),
            'period_end' => $metric->period_end?->toDateString(),
            'source' => $metric->source,
            'notes' => $metric->notes,
            'recorded_at' => $metric->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, NpoImpactMetric>  $metrics
     * @return array<int, array<string, mixed>>
     */
    public function payloads(Collection $metrics): array
    {
        return $metrics
            ->map(fn (NpoImpactMetric $metric): array => $this->payload($metric))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function metricKey(array $input): string
    {
        $candidate = trim((string) ($input['metric_key'] ?? ''));
        if ($candidate === '') {
            $candidate = trim((string) ($input['metric_label'] ?? ''));
        }

        $key = Str::of($candidate)->lower()->replace(['-', ' '], '_')->replaceMatches('/[^a-z0-9_]/', '')->squish()->value();

        if ($key === '') {
            throw new InvalidArgumentException('Impact metric key is required.');
        }

        return $key;
    }

    private function nonNegativeNumber(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$label} is required.");
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new InvalidArgumentException("{$label} must be zero or greater.");
        }

        return round($number, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
