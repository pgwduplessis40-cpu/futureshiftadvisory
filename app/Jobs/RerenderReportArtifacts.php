<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RerenderReportArtifacts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 420;

    public function __construct(
        public readonly string $reportId,
        public readonly string $requestToken,
    ) {}

    public function handle(ReportComposer $reports): void
    {
        $report = Report::query()->find($this->reportId);

        if (! $report instanceof Report) {
            return;
        }

        $reports->rerenderQueuedArtifacts($report, $this->requestToken);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
