<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\BusinessHealthSnapshot;
use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class BusinessHealthSnapshotWriter
{
    public function __construct(private readonly BusinessHealthRadarBuilder $builder) {}

    /**
     * @return EloquentCollection<int, BusinessHealthSnapshot>
     *
     * @throws Throwable
     */
    public function recompute(Client $client, ?CarbonInterface $capturedAt = null): EloquentCollection
    {
        $capturedAt ??= now();
        $batchId = (string) Str::uuid();
        $rows = $this->builder->rowsFor($client, $batchId, $capturedAt);

        return DB::transaction(function () use ($client, $batchId, $rows): EloquentCollection {
            foreach ($rows as $row) {
                BusinessHealthSnapshot::query()->create($row);
            }

            return BusinessHealthSnapshot::query()
                ->where('client_id', $client->getKey())
                ->where('assessment_batch_id', $batchId)
                ->get()
                ->sortBy(fn (BusinessHealthSnapshot $snapshot): int => array_search($snapshot->dimension, BusinessHealthSnapshot::dimensions(), true))
                ->values();
        });
    }
}
