<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\Storage\QuarantinedDocumentRescanner;
use App\Support\RequestContext;
use Illuminate\Console\Command;

final class RescanQuarantinedDocuments extends Command
{
    protected $signature = 'fsa:rescan-quarantined-documents
                            {--limit=100 : Maximum quarantined rows to inspect.}
                            {--probe : Verify the scanner rejects the standard antivirus test signature.}
                            {--fail-on-error : Return a non-zero exit code if any document cannot be scanned.}';

    protected $description = 'Verify malware scanning and recover documents quarantined by transient scanner errors.';

    public function handle(
        QuarantinedDocumentRescanner $rescanner,
        RequestContext $requestContext,
    ): int {
        return $requestContext->withSystemContext(
            fn (): int => $this->runInSystemContext($rescanner),
        );
    }

    private function runInSystemContext(QuarantinedDocumentRescanner $rescanner): int
    {
        if ((bool) $this->option('probe')) {
            $probe = $rescanner->probe();

            if (! $probe->isInfected()) {
                $this->error($probe->message ?? 'Malware scanner did not reject the test signature.');

                return self::FAILURE;
            }

            $this->info('Malware scanner probe passed.');
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $documents = Document::query()
            ->where('scanner_result', Document::SCANNER_ERROR)
            ->oldest()
            ->limit($limit)
            ->get()
            ->groupBy(fn (Document $document): string => $this->duplicateKey($document));

        $clean = 0;
        $infected = 0;
        $errors = 0;
        $duplicatesRemoved = 0;

        foreach ($documents as $duplicates) {
            /** @var Document $document */
            $document = $duplicates->first();
            $canonical = $this->matchingCleanDocument($document) ?? $document;
            $result = $canonical->scanner_result === Document::SCANNER_CLEAN
                ? null
                : $rescanner->rescan($canonical);

            if ($result?->isClean()) {
                $clean++;
            } elseif ($result?->isInfected()) {
                $infected++;
            } elseif ($result?->isError()) {
                $errors++;
            }

            if ($result === null || ! $result->isError()) {
                foreach ($duplicates as $duplicate) {
                    if (
                        (string) $duplicate->getKey() !== (string) $canonical->getKey()
                        && $rescanner->discardDuplicate($duplicate, $canonical)
                    ) {
                        $duplicatesRemoved++;
                    }
                }
            }
        }

        $this->info(sprintf(
            'Quarantined document rescan: %d recovered, %d infected, %d still unavailable, %d duplicates removed.',
            $clean,
            $infected,
            $errors,
            $duplicatesRemoved,
        ));

        return $errors > 0 && (bool) $this->option('fail-on-error')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function duplicateKey(Document $document): string
    {
        return implode('|', [
            $document->client_id,
            $document->entrepreneur_profile_id,
            $document->npo_engagement_id,
            $document->category,
            $document->sha256 ?: $document->getKey(),
        ]);
    }

    private function matchingCleanDocument(Document $document): ?Document
    {
        if (! is_string($document->sha256) || $document->sha256 === '') {
            return null;
        }

        $query = Document::query()
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->where('category', $document->category)
            ->where('sha256', $document->sha256);

        foreach ([
            'client_id' => $document->client_id,
            'entrepreneur_profile_id' => $document->entrepreneur_profile_id,
            'npo_engagement_id' => $document->npo_engagement_id,
        ] as $column => $value) {
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        return $query->oldest()->first();
    }
}
