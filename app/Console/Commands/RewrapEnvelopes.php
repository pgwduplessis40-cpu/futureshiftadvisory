<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CryptoRotation;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class RewrapEnvelopes extends Command
{
    protected $signature = 'envelopes:rewrap
                            {--target= : Envelope target version. Defaults to the active write version.}
                            {--dry-run : Inspect and report without writing rows.}';

    protected $description = 'Rewrap stored KeyEnvelope payloads into the active envelope version.';

    public function handle(KeyEnvelope $envelope, AuditWriter $audit): int
    {
        $target = $this->targetVersion($envelope);
        $toAlg = $envelope->algorithmForVersion($target);
        $dryRun = (bool) $this->option('dry-run');
        $runId = (string) Str::uuid();
        $counts = [
            'rewrapped' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($this->sources() as $source) {
            if (! $this->sourceExists($source['table'], $source['column'])) {
                continue;
            }

            DB::table($source['table'])
                ->select('id', $source['column'])
                ->whereNotNull($source['column'])
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($source, $envelope, $target, $toAlg, $dryRun, $runId, &$counts): void {
                    foreach ($rows as $row) {
                        $this->rewrapRow($row, $source, $envelope, $target, $toAlg, $dryRun, $runId, $counts);
                    }
                });
        }

        $audit->record(
            action: $dryRun ? 'crypto.envelopes_rewrap_dry_run' : 'crypto.envelopes_rewrapped',
            context: [
                'run_id' => $runId,
                'target_version' => $target,
                'target_alg' => $toAlg,
                'counts' => $counts,
            ],
        );

        $this->info(sprintf(
            'Envelope rewrap run %s completed: %d rewrapped, %d skipped, %d failed.',
            $runId,
            $counts['rewrapped'],
            $counts['skipped'],
            $counts['failed'],
        ));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{table: string, column: string, meta_column?: string|null}  $source
     * @param  array{rewrapped: int, skipped: int, failed: int}  $counts
     */
    private function rewrapRow(
        object $row,
        array $source,
        KeyEnvelope $envelope,
        int $target,
        string $toAlg,
        bool $dryRun,
        string $runId,
        array &$counts,
    ): void {
        $startedAt = now();
        $ciphertext = (string) $row->{$source['column']};
        $sourceId = (string) $row->id;
        $fromMeta = null;
        $toMeta = null;
        $status = CryptoRotation::STATUS_FAILED;
        $error = null;

        try {
            $fromMeta = $envelope->inspect($ciphertext);

            if ($fromMeta['v'] === $target) {
                $status = CryptoRotation::STATUS_SKIPPED;
                $toMeta = $fromMeta;
                $counts['skipped']++;

                return;
            }

            $plaintext = $envelope->decrypt($ciphertext);
            $rewrapped = $envelope->encryptForVersion($plaintext, $target);
            $toMeta = $envelope->inspect($rewrapped);

            if (! $dryRun) {
                $updates = [
                    $source['column'] => $rewrapped,
                    'updated_at' => now(),
                ];

                $metaColumn = $source['meta_column'] ?? null;
                if (is_string($metaColumn) && $metaColumn !== '' && Schema::hasColumn($source['table'], $metaColumn)) {
                    $updates[$metaColumn] = json_encode($toMeta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                }

                DB::table($source['table'])
                    ->where('id', $sourceId)
                    ->update($updates);
            }

            $status = CryptoRotation::STATUS_REWRAPPED;
            $counts['rewrapped']++;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $counts['failed']++;
        } finally {
            if (! $dryRun) {
                $this->recordRotation(
                    runId: $runId,
                    source: $source,
                    sourceId: $sourceId,
                    fromMeta: $fromMeta,
                    toMeta: $toMeta,
                    target: $target,
                    toAlg: $toAlg,
                    status: $status,
                    error: $error,
                    startedAt: $startedAt,
                );
            }
        }
    }

    /**
     * @param  array{table: string, column: string, meta_column?: string|null}  $source
     * @param  array{v: int, alg: string, kid: string}|null  $fromMeta
     * @param  array{v: int, alg: string, kid: string}|null  $toMeta
     */
    private function recordRotation(
        string $runId,
        array $source,
        string $sourceId,
        ?array $fromMeta,
        ?array $toMeta,
        int $target,
        string $toAlg,
        string $status,
        ?string $error,
        Carbon $startedAt,
    ): void {
        $idempotencyKey = hash('sha256', implode('|', [
            $source['table'],
            $source['column'],
            $sourceId,
            $target,
            $fromMeta['v'] ?? 'unknown',
            $fromMeta['kid'] ?? 'unknown',
        ]));

        CryptoRotation::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'run_id' => $runId,
                'rotation_type' => 'envelope_rewrap',
                'source_table' => $source['table'],
                'source_column' => $source['column'],
                'source_id' => $sourceId,
                'from_version' => $fromMeta['v'] ?? null,
                'from_alg' => $fromMeta['alg'] ?? null,
                'from_kid' => $fromMeta['kid'] ?? null,
                'to_version' => $toMeta['v'] ?? $target,
                'to_alg' => $toMeta['alg'] ?? $toAlg,
                'to_kid' => $toMeta['kid'] ?? null,
                'from_meta' => $fromMeta,
                'to_meta' => $toMeta,
                'status' => $status,
                'error_message' => $error,
                'started_at' => $startedAt,
                'completed_at' => now(),
            ],
        );
    }

    private function targetVersion(KeyEnvelope $envelope): int
    {
        $target = $this->option('target');

        if ($target === null || $target === '') {
            return $envelope->writeVersion();
        }

        return (int) $target;
    }

    private function sourceExists(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    /**
     * @return array<int, array{table: string, column: string, meta_column?: string|null}>
     */
    private function sources(): array
    {
        return [
            ['table' => 'mfa_factors', 'column' => 'secret_envelope'],
            ['table' => 'mfa_factors', 'column' => 'recovery_codes_envelope'],
            ['table' => 'terms_acceptances', 'column' => 'signed_pdf_sha256_envelope', 'meta_column' => 'signed_pdf_envelope_meta'],
            ['table' => 'accounting_connections', 'column' => 'token_envelope', 'meta_column' => 'token_envelope_meta'],
            ['table' => 'proposals', 'column' => 'signature_evidence_sha256_envelope', 'meta_column' => 'signature_envelope_meta'],
            ['table' => 'payment_authorities', 'column' => 'gateway_token_envelope'],
            ['table' => 'receipts', 'column' => 'receipt_sha256_envelope', 'meta_column' => 'receipt_envelope_meta'],
            ['table' => 'panel_agreements', 'column' => 'pdf_sha256_envelope', 'meta_column' => 'pdf_envelope_meta'],
            ['table' => 'nz_tool_connections', 'column' => 'token_envelope', 'meta_column' => 'token_envelope_meta'],
        ];
    }
}
