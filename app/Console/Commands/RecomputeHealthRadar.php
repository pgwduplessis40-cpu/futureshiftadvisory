<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\Audit\AuditWriter;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class RecomputeHealthRadar extends Command
{
    protected $signature = 'fsa:recompute-health-radar {client? : Optional client UUID to recompute.}';

    protected $description = 'Recompute client portal business health radar snapshots.';

    public function handle(
        BusinessHealthSnapshotWriter $writer,
        AuditWriter $audit,
        RequestContext $context,
    ): int {
        $context->apply('system', []);

        $clientId = $this->argument('client');
        $query = Client::query()->orderBy('id');

        if (is_string($clientId) && $clientId !== '') {
            $query->whereKey($clientId);
        }

        $count = 0;
        $query->each(function (Client $client) use ($writer, $audit, &$count): void {
            $snapshots = $writer->recompute($client);
            $batchId = (string) $snapshots->first()?->assessment_batch_id;

            $audit->record('business_health.recomputed', subject: $client, actor: null, after: [
                'assessment_batch_id' => $batchId,
                'dimensions' => $snapshots
                    ->pluck('dimension')
                    ->values()
                    ->all(),
            ]);

            $count++;
        });

        if ($count === 0 && is_string($clientId) && $clientId !== '') {
            $this->error('Client not found.');

            return self::FAILURE;
        }

        $this->info("Recomputed {$count} business health radar batch(es).");

        return self::SUCCESS;
    }
}
