<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('risk_costs as risk')
            ->join('analysis_findings as finding', 'finding.id', '=', 'risk.analysis_finding_id')
            ->join('dd_workstreams as workstream', 'workstream.analysis_run_id', '=', 'finding.analysis_run_id')
            ->select([
                'risk.id',
                'risk.client_id',
                'risk.superseded_at',
                'risk.created_at',
                'risk.pv_of_cost',
                'finding.title',
                'finding.body',
                'finding.attributions',
                'workstream.dd_engagement_id',
                'workstream.workstream',
            ])
            ->orderBy('risk.client_id')
            ->orderByDesc('risk.created_at')
            ->orderByDesc('risk.pv_of_cost')
            ->orderByDesc('risk.id')
            ->get()
            ->unique('id')
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        $fingerprints = [];

        $rows->each(function (object $row) use (&$fingerprints): void {
            $fingerprint = $this->runtimeDdRiskFingerprint($row);
            $fingerprints[(string) $row->id] = $fingerprint;

            DB::table('risk_costs')
                ->where('id', $row->id)
                ->update(['source_fingerprint' => $fingerprint]);
        });

        $this->supersedeActiveDuplicates($rows, $fingerprints);
    }

    public function down(): void
    {
        // Data-only stabilization; old transient DD risk fingerprints are not restored.
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, string>  $fingerprints
     */
    private function supersedeActiveDuplicates(Collection $rows, array $fingerprints): void
    {
        $now = now();
        $clientsByFingerprint = $rows
            ->filter(fn (object $row): bool => $row->superseded_at === null)
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->client_id.'|'.$fingerprints[(string) $row->id] => [
                    'client_id' => (string) $row->client_id,
                    'fingerprint' => $fingerprints[(string) $row->id],
                ],
            ])
            ->values();

        $clientsByFingerprint->each(function (array $scope) use ($now): void {
            DB::table('risk_costs')
                ->where('client_id', $scope['client_id'])
                ->where('source_fingerprint', $scope['fingerprint'])
                ->whereNull('superseded_at')
                ->orderByDesc('created_at')
                ->orderByDesc('pv_of_cost')
                ->orderByDesc('id')
                ->skip(1)
                ->get('id')
                ->each(function (object $duplicate) use ($now): void {
                    DB::table('risk_costs')
                        ->where('id', $duplicate->id)
                        ->whereNull('superseded_at')
                        ->update([
                            'superseded_at' => $now,
                            'superseded_reason' => 'stable_dd_risk_fingerprint_duplicate',
                        ]);
                });
        });
    }

    private function runtimeDdRiskFingerprint(object $row): string
    {
        $title = mb_strtolower(trim((string) ($row->title ?? 'Risk cost')));
        $sourceKey = $this->runtimeDdRiskSourceKey($row);

        return hash('sha256', implode('|', [$title, mb_strtolower(trim($sourceKey))]));
    }

    private function runtimeDdRiskSourceKey(object $row): string
    {
        $stableSource = $this->primaryStableSourceReference($row->attributions ?? null);
        $body = mb_strtolower(trim((string) ($row->body ?? '')));
        $basis = implode('|', [
            (string) $row->dd_engagement_id,
            (string) ($row->workstream ?: 'general'),
            mb_strtolower(trim((string) ($row->title ?? ''))),
            $stableSource ?: $body,
        ]);

        return 'dd_risk:'.hash('sha256', $basis);
    }

    private function primaryStableSourceReference(mixed $attributions): string
    {
        if (is_string($attributions)) {
            $decoded = json_decode($attributions, true);
            $attributions = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($attributions)) {
            return '';
        }

        foreach ($attributions as $attribution) {
            if (! is_array($attribution) || ! is_string($attribution['source_reference'] ?? null)) {
                continue;
            }

            $source = (string) $attribution['source_reference'];
            if ($source !== '' && ! str_starts_with($source, 'analysis_finding:')) {
                return $source;
            }
        }

        return '';
    }
};
