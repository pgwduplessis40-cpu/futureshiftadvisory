<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PanelAgreement;
use App\Services\Panels\PanelOnboarding;
use Illuminate\Console\Command;
use Throwable;

final class RefreshPanelAgreementPdfs extends Command
{
    protected $signature = 'panels:refresh-agreement-pdfs
        {--agreement=* : Refresh one or more signed panel agreement IDs}
        {--all : Refresh every signed panel agreement}';

    protected $description = 'Re-render signed panel agreement PDFs with the current branded agreement layout.';

    public function handle(PanelOnboarding $onboarding): int
    {
        $agreementIds = collect($this->option('agreement'))
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->values();
        $refreshAll = (bool) $this->option('all');

        if (! $refreshAll && $agreementIds->isEmpty()) {
            $this->error('Pass --agreement=<id> or --all.');

            return self::FAILURE;
        }

        $query = PanelAgreement::query()
            ->with(['panelMember.user', 'signedBy'])
            ->where('status', PanelAgreement::STATUS_SIGNED)
            ->when(! $refreshAll, fn ($query) => $query->whereIn('id', $agreementIds->all()));

        $total = (clone $query)->count();
        $refreshed = 0;
        $failed = 0;

        $query->orderBy('id')->each(function (PanelAgreement $agreement) use ($onboarding, &$refreshed, &$failed): void {
            try {
                $onboarding->refreshSignedAgreementPdf($agreement);
                $refreshed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error(sprintf('Failed to refresh panel agreement %s: %s', $agreement->getKey(), $exception->getMessage()));
            }
        });

        $this->info(sprintf('Refreshed %d of %d signed panel agreement PDFs.', $refreshed, $total));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
