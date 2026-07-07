<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshFingerprintsAndSupersedeActiveDuplicates(
            table: 'improvement_opportunities',
            defaultSource: 'advisor:improvement_opportunity',
            valueColumn: 'pv_of_impact',
        );
        $this->refreshFingerprintsAndSupersedeActiveDuplicates(
            table: 'risk_costs',
            defaultSource: 'advisor:risk_cost',
            valueColumn: 'pv_of_cost',
        );
    }

    public function down(): void
    {
        // Data-only stabilization; old transient-finding fingerprints are not restored.
    }

    private function refreshFingerprintsAndSupersedeActiveDuplicates(
        string $table,
        string $defaultSource,
        string $valueColumn,
    ): void {
        $now = now();
        $rows = DB::table($table)
            ->select('id', 'client_id', 'analysis_finding_id', 'title', 'source_attributions', 'superseded_at', 'created_at', $valueColumn)
            ->orderBy('client_id')
            ->orderByDesc('created_at')
            ->orderByDesc($valueColumn)
            ->orderByDesc('id')
            ->get();
        $findingAttributions = $this->findingAttributions($rows);

        $rows->each(function (object $row) use ($table, $defaultSource, $findingAttributions): void {
            DB::table($table)
                ->where('id', $row->id)
                ->update([
                    'source_fingerprint' => $this->fingerprint($row, $defaultSource, $findingAttributions[(string) $row->analysis_finding_id] ?? null),
                ]);
        });

        $rows
            ->filter(fn (object $row): bool => $row->superseded_at === null)
            ->groupBy(fn (object $row): string => (string) $row->client_id.'|'.$this->fingerprint(
                $row,
                $defaultSource,
                $findingAttributions[(string) $row->analysis_finding_id] ?? null,
            ))
            ->each(function (Collection $duplicates) use ($table, $now): void {
                $duplicates
                    ->values()
                    ->skip(1)
                    ->each(function (object $row) use ($table, $now): void {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->whereNull('superseded_at')
                            ->update([
                                'superseded_at' => $now,
                                'superseded_reason' => 'stable_fingerprint_duplicate',
                            ]);
                    });
            });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function findingAttributions(Collection $rows): array
    {
        $findingIds = $rows
            ->pluck('analysis_finding_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        if ($findingIds->isEmpty()) {
            return [];
        }

        return DB::table('analysis_findings')
            ->whereIn('id', $findingIds)
            ->pluck('attributions', 'id')
            ->all();
    }

    private function fingerprint(object $row, string $defaultSource, mixed $findingAttributions): string
    {
        $source = $this->primaryStableSourceReference($row->source_attributions ?? null)
            ?: $this->primaryStableSourceReference($findingAttributions)
            ?: $defaultSource;

        return hash('sha256', implode('|', [
            mb_strtolower(trim((string) ($row->title ?? ''))),
            $source,
        ]));
    }

    private function primaryStableSourceReference(mixed $sourceAttributions): string
    {
        if (is_string($sourceAttributions)) {
            $decoded = json_decode($sourceAttributions, true);
            $sourceAttributions = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($sourceAttributions)) {
            return '';
        }

        foreach ($sourceAttributions as $attribution) {
            if (! is_array($attribution) || ! is_string($attribution['source_reference'] ?? null)) {
                continue;
            }

            $source = mb_strtolower(trim((string) $attribution['source_reference']));
            if ($source !== '' && ! str_starts_with($source, 'analysis_finding:')) {
                return $source;
            }
        }

        return '';
    }
};
