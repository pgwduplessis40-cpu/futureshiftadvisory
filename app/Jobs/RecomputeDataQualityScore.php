<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Client;
use App\Services\DataQuality\DataQualityScorer;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecomputeDataQualityScore implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $clientId) {}

    public function handle(DataQualityScorer $scorer, RequestContext $context): void
    {
        $context->apply('system', []);

        $client = Client::query()->find($this->clientId);

        if (! $client instanceof Client) {
            return;
        }

        $score = $scorer->score($client);

        if ($client->data_quality === $score->level) {
            return;
        }

        $client->forceFill([
            'data_quality' => $score->level,
        ])->save();
    }
}
