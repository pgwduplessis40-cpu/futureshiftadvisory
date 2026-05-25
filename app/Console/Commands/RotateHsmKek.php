<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CryptoRotation;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\Hsm\HsmKeyManager;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RotateHsmKek extends Command
{
    protected $signature = 'hsm:rotate-kek
                            {--key-id= : Optional target HSM key identifier. If omitted, the driver chooses.}
                            {--dry-run : Report the active driver/key without rotating.}';

    protected $description = 'Rotate the active HSM KEK reference used by v2 envelope wrapping.';

    public function handle(HsmKeyManager $hsm, KeyEnvelope $envelope, AuditWriter $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $before = $hsm->activeKeyId();
        $after = $dryRun ? $before : $hsm->rotateKek($this->optionString('key-id'));
        $runId = (string) Str::uuid();

        if (! $dryRun) {
            CryptoRotation::query()->updateOrCreate(
                ['idempotency_key' => hash('sha256', 'hsm_kek_rotation|'.$hsm->driver().'|'.$before.'|'.$after)],
                [
                    'run_id' => $runId,
                    'rotation_type' => 'hsm_kek_rotation',
                    'from_version' => KeyEnvelope::VERSION_V2,
                    'from_alg' => KeyEnvelope::ALG_V2,
                    'from_kid' => $before,
                    'to_version' => KeyEnvelope::VERSION_V2,
                    'to_alg' => $envelope->algorithmForVersion(KeyEnvelope::VERSION_V2),
                    'to_kid' => $after,
                    'from_meta' => ['hsm_driver' => $hsm->driver(), 'hsm_key_id' => $before],
                    'to_meta' => ['hsm_driver' => $hsm->driver(), 'hsm_key_id' => $after],
                    'status' => CryptoRotation::STATUS_ROTATED,
                    'started_at' => now(),
                    'completed_at' => now(),
                ],
            );
        }

        $audit->record(
            action: $dryRun ? 'crypto.hsm_kek_rotation_dry_run' : 'crypto.hsm_kek_rotated',
            context: [
                'run_id' => $runId,
                'driver' => $hsm->driver(),
                'from_kid' => $before,
                'to_kid' => $after,
            ],
        );

        $this->info(sprintf(
            'HSM KEK %s: driver=%s from=%s to=%s.',
            $dryRun ? 'dry run' : 'rotated',
            $hsm->driver(),
            $before,
            $after,
        ));

        return self::SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
