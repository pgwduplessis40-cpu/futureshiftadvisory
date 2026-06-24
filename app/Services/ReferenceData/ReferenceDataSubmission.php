<?php

declare(strict_types=1);

namespace App\Services\ReferenceData;

use App\Models\Document;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\EconomicData\EconomicIndicatorRefresher;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Pv\IndustryWaccRefresher;
use App\Services\Pv\ValuationMultipleRefresher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReferenceDataSubmission
{
    /**
     * Statuses that are still awaiting a final outcome and should block exact
     * duplicate manual reference data submissions.
     */
    public const PENDING_REVIEW_STATUSES = [
        LearningUpdate::STATUS_DETECTED,
        LearningUpdate::STATUS_STAGED,
        LearningUpdate::STATUS_APPROVED,
        LearningUpdate::STATUS_DEFERRED,
    ];

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(
        string $dataset,
        array $payload,
        CarbonInterface $asAt,
        string $source,
        User $actor,
        ?Document $evidenceDocument = null,
    ): ReferenceDataEntry {
        if (! in_array($dataset, ReferenceDataEntry::datasets(), true)) {
            throw new InvalidArgumentException("Unsupported reference dataset [{$dataset}].");
        }

        $payload = $this->normalisePayload($dataset, $payload, $asAt, $source);
        $storesEvidenceDocument = Schema::hasColumn('reference_data_entries', 'evidence_document_id');
        $evidenceDocument = $storesEvidenceDocument ? $evidenceDocument : null;
        $signalKey = $this->signalKey($dataset, $source, $asAt, $payload);

        $existing = $this->existingPendingEntry($signalKey);
        if ($existing instanceof ReferenceDataEntry) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $asAt, $dataset, $evidenceDocument, $payload, $signalKey, $source, $storesEvidenceDocument): ReferenceDataEntry {
            $existing = $this->existingPendingEntry($signalKey);
            if ($existing instanceof ReferenceDataEntry) {
                return $existing;
            }

            $learningUpdate = $this->learningUpdate($dataset, $payload, $asAt, $source, $actor, $evidenceDocument, $signalKey);

            $entryAttributes = [
                'dataset' => $dataset,
                'payload' => $payload,
                'as_at' => $asAt->toDateString(),
                'source' => $source,
                'entered_by_user_id' => $actor->getKey(),
                'learning_update_id' => $learningUpdate->getKey(),
            ];

            if ($storesEvidenceDocument) {
                $entryAttributes['evidence_document_id'] = $evidenceDocument?->getKey();
            }

            /** @var ReferenceDataEntry $entry */
            $entry = ReferenceDataEntry::query()->create($entryAttributes);

            $after = [
                'dataset' => $dataset,
                'as_at' => $entry->as_at?->toDateString(),
                'source' => $source,
                'learning_update_id' => $learningUpdate->getKey(),
            ];

            if ($storesEvidenceDocument) {
                $after['evidence_document_id'] = $evidenceDocument?->getKey();
            }

            $this->audit->record('reference_data.submitted', subject: $entry, actor: $actor, after: $after);

            return $entry->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalisePayload(string $dataset, array $payload, CarbonInterface $asAt, string $source): array
    {
        return match ($dataset) {
            ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR => $this->economicPayload($payload, $asAt, $source),
            ReferenceDataEntry::DATASET_VALUATION_MULTIPLE => $this->valuationPayload($payload, $asAt, $source),
            ReferenceDataEntry::DATASET_INDUSTRY_WACC => $this->waccPayload($payload, $asAt, $source),
            ReferenceDataEntry::DATASET_CPB_BENCHMARK => $this->cpbPayload($payload, $source),
            default => throw new InvalidArgumentException("Unsupported reference dataset [{$dataset}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function economicPayload(array $payload, CarbonInterface $asAt, string $source): array
    {
        $indicator = $this->requiredSlug($payload, 'indicator');
        $value = $this->requiredFloat($payload, 'value');
        $unit = trim((string) ($payload['unit'] ?? ''));
        $label = trim((string) ($payload['label'] ?? Str::headline($indicator)));

        if ($unit === '') {
            throw new InvalidArgumentException('Economic indicator unit is required.');
        }

        return [
            ...$payload,
            'indicator' => $indicator,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'period_date' => (string) ($payload['period_date'] ?? $asAt->toDateString()),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function valuationPayload(array $payload, CarbonInterface $asAt, string $source): array
    {
        $low = $this->requiredFloat($payload, 'multiple_low');
        $mid = $this->requiredFloat($payload, 'multiple_mid');
        $high = $this->requiredFloat($payload, 'multiple_high');

        if ($low <= 0 || $mid <= 0 || $high <= 0 || $low > $mid || $mid > $high) {
            throw new InvalidArgumentException('Valuation multiples must be positive and ordered low <= mid <= high.');
        }

        return [
            ...$payload,
            'industry_code' => strtoupper(trim((string) ($payload['industry_code'] ?? ''))),
            'industry_label' => (string) ($payload['industry_label'] ?? Str::headline((string) ($payload['industry_code'] ?? 'industry'))),
            'metric' => strtolower(trim((string) ($payload['metric'] ?? 'ebitda'))),
            'multiple_low' => $low,
            'multiple_mid' => $mid,
            'multiple_high' => $high,
            'quarter' => strtoupper((string) ($payload['quarter'] ?? $this->quarter($asAt))),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function waccPayload(array $payload, CarbonInterface $asAt, string $source): array
    {
        $wacc = $this->requiredFloat($payload, 'wacc_rate');
        if ($wacc <= 0 || $wacc >= 1) {
            throw new InvalidArgumentException('WACC rate must be a decimal rate between 0 and 1.');
        }

        return [
            ...$payload,
            'industry_code' => strtoupper(trim((string) ($payload['industry_code'] ?? ''))),
            'industry_label' => (string) ($payload['industry_label'] ?? Str::headline((string) ($payload['industry_code'] ?? 'industry'))),
            'wacc_rate' => $wacc,
            'quarter' => strtoupper((string) ($payload['quarter'] ?? $this->quarter($asAt))),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cpbPayload(array $payload, string $source): array
    {
        $rows = isset($payload['benchmarks']) && is_array($payload['benchmarks'])
            ? $payload['benchmarks']
            : (array_is_list($payload) ? $payload : [$payload]);

        $benchmarks = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'programme_type' => $this->requiredSlug($row, 'programme_type'),
                    'size_band' => $this->requiredSlug($row, 'size_band'),
                    'cost_per_beneficiary' => $this->requiredFloat($row, 'cost_per_beneficiary'),
                    'sample_size' => max(0, (int) ($row['sample_size'] ?? $row['comparable_organisations'] ?? $row['n'] ?? 0)),
                ];
            })
            ->values()
            ->all();

        if ($benchmarks === []) {
            throw new InvalidArgumentException('At least one CPB benchmark row is required.');
        }

        return [
            'benchmarks' => $benchmarks,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function learningUpdate(
        string $dataset,
        array $payload,
        CarbonInterface $asAt,
        string $source,
        User $actor,
        ?Document $evidenceDocument,
        string $signalKey,
    ): LearningUpdate {
        $layerId = match ($dataset) {
            ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR => EconomicIndicatorRefresher::LAYER_ID,
            ReferenceDataEntry::DATASET_VALUATION_MULTIPLE => ValuationMultipleRefresher::LAYER_ID,
            ReferenceDataEntry::DATASET_INDUSTRY_WACC => IndustryWaccRefresher::LAYER_ID,
            ReferenceDataEntry::DATASET_CPB_BENCHMARK => LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS,
            default => throw new InvalidArgumentException("Unsupported reference dataset [{$dataset}]."),
        };

        return LearningUpdate::query()->create([
            'layer_id' => $layerId,
            'source' => [
                'type' => 'manual_reference_data',
                'dataset' => $dataset,
                'source' => $source,
                'entered_by_user_id' => $actor->getKey(),
                'evidence_document_id' => $evidenceDocument?->getKey(),
                'signal_key' => $signalKey,
            ],
            'summary' => $this->summary($dataset, $payload, $asAt),
            'proposed_change' => [
                'action' => 'project_manual_reference_data',
                'dataset' => $dataset,
                'payload' => $payload,
                'benchmarks' => $dataset === ReferenceDataEntry::DATASET_CPB_BENCHMARK ? $payload['benchmarks'] : null,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'reference_dataset' => $dataset,
                'manual_admin_entry' => true,
            ],
            'clients_affected' => 0,
            'magnitude' => 'medium',
            'confidence' => 0.9,
            'evidence' => [
                'as_at' => $asAt->toDateString(),
                'source' => $source,
                'payload' => $payload,
                'evidence_document' => $evidenceDocument instanceof Document ? [
                    'id' => $evidenceDocument->getKey(),
                    'filename' => $evidenceDocument->original_filename,
                    'mime_type' => $evidenceDocument->mime_type,
                    'sha256' => $evidenceDocument->sha256,
                ] : null,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signalKey(string $dataset, string $source, CarbonInterface $asAt, array $payload): string
    {
        return hash('sha256', $dataset.'|'.$source.'|'.$asAt->toDateString().'|'.json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function existingPendingEntry(string $signalKey): ?ReferenceDataEntry
    {
        $update = LearningUpdate::query()
            ->whereIn('status', self::PENDING_REVIEW_STATUSES)
            ->where('source->type', 'manual_reference_data')
            ->where('source->signal_key', $signalKey)
            ->latest()
            ->first();

        if (! $update instanceof LearningUpdate) {
            return null;
        }

        return ReferenceDataEntry::query()
            ->where('learning_update_id', $update->getKey())
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summary(string $dataset, array $payload, CarbonInterface $asAt): string
    {
        $label = match ($dataset) {
            ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR => 'Economic indicator',
            ReferenceDataEntry::DATASET_VALUATION_MULTIPLE => 'Valuation multiple',
            ReferenceDataEntry::DATASET_INDUSTRY_WACC => 'Industry WACC',
            ReferenceDataEntry::DATASET_CPB_BENCHMARK => 'Cost-per-beneficiary benchmark',
            default => 'Reference data',
        };

        return $label.' manual reference data submitted for '.$asAt->toDateString().'.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredSlug(array $payload, string $key): string
    {
        $value = strtolower(trim(str_replace([' ', '-'], '_', (string) ($payload[$key] ?? ''))));
        if ($value === '') {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredFloat(array $payload, string $key): float
    {
        if (! is_numeric($payload[$key] ?? null)) {
            throw new InvalidArgumentException("{$key} must be numeric.");
        }

        return round((float) $payload[$key], 6);
    }

    private function quarter(CarbonInterface $date): string
    {
        return sprintf('%dQ%d', $date->year, (int) ceil($date->month / 3));
    }
}
