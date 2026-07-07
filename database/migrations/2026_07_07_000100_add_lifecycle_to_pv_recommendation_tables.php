<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('improvement_opportunities', function (Blueprint $table): void {
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->string('superseded_reason', 120)->nullable();
            $table->index(['client_id', 'source_fingerprint', 'superseded_at'], 'improvement_opportunities_active_source_idx');
        });

        Schema::table('risk_costs', function (Blueprint $table): void {
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->string('superseded_reason', 120)->nullable();
            $table->index(['client_id', 'source_fingerprint', 'superseded_at'], 'risk_costs_active_source_idx');
        });

        $this->backfillFingerprintsAndSupersedeDuplicates('improvement_opportunities', 'pv_of_impact');
        $this->backfillFingerprintsAndSupersedeDuplicates('risk_costs', 'pv_of_cost');
    }

    public function down(): void
    {
        Schema::table('risk_costs', function (Blueprint $table): void {
            $table->dropIndex('risk_costs_active_source_idx');
            $table->dropColumn(['source_fingerprint', 'superseded_at', 'superseded_reason']);
        });

        Schema::table('improvement_opportunities', function (Blueprint $table): void {
            $table->dropIndex('improvement_opportunities_active_source_idx');
            $table->dropColumn(['source_fingerprint', 'superseded_at', 'superseded_reason']);
        });
    }

    private function backfillFingerprintsAndSupersedeDuplicates(string $table, string $valueColumn): void
    {
        $now = now();
        $rows = DB::table($table)
            ->select('id', 'client_id', 'analysis_finding_id', 'title', 'source_attributions', 'created_at', $valueColumn)
            ->orderBy('client_id')
            ->orderByDesc('created_at')
            ->orderByDesc($valueColumn)
            ->get();

        $rows->each(function (object $row) use ($table): void {
            DB::table($table)
                ->where('id', $row->id)
                ->update(['source_fingerprint' => $this->fingerprint($row, $table)]);
        });

        $rows
            ->groupBy(fn (object $row): string => (string) $row->client_id.'|'.$this->fingerprint($row, $table))
            ->each(function (Collection $duplicates) use ($table, $now): void {
                $duplicates
                    ->values()
                    ->skip(1)
                    ->each(function (object $row) use ($table, $now): void {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([
                                'superseded_at' => $now,
                                'superseded_reason' => 'duplicate_backfill',
                            ]);
                    });
            });
    }

    private function fingerprint(object $row, string $table): string
    {
        $source = $this->primarySourceReference($row->source_attributions ?? null)
            ?: $this->defaultSourceReference($table);

        return hash('sha256', implode('|', [
            (string) ($row->analysis_finding_id ?? ''),
            mb_strtolower(trim((string) ($row->title ?? ''))),
            $source,
        ]));
    }

    private function primarySourceReference(mixed $sourceAttributions): string
    {
        if (is_string($sourceAttributions)) {
            $decoded = json_decode($sourceAttributions, true);
            $sourceAttributions = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($sourceAttributions)) {
            return '';
        }

        foreach ($sourceAttributions as $attribution) {
            if (is_array($attribution) && is_string($attribution['source_reference'] ?? null)) {
                return mb_strtolower(trim((string) $attribution['source_reference']));
            }
        }

        return '';
    }

    private function defaultSourceReference(string $table): string
    {
        return $table === 'risk_costs'
            ? 'advisor:risk_cost'
            : 'advisor:improvement_opportunity';
    }
};
