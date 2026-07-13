<?php

declare(strict_types=1);

namespace App\Services\QuoteSources;

use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\IntegrationScope;
use App\Models\QuoteSourceExtraction;
use App\Models\QuoteSourceExtractionDocument;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\QuoteSourceExtractionClient;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Services\Audit\AuditWriter;
use App\Services\Integrations\IntegrationScopeService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class QuoteSourceExtractor
{
    private const QUOTE_CLAIM = 'Implementation plan describing the client systems, manual processes, and requested connections, used to scope an integration quote.';

    public function __construct(
        private readonly DocumentVerifier $verifier,
        private readonly AiClient $ai,
        private readonly PromptRegistry $prompts,
        private readonly QuoteSourceDocumentText $documentText,
        private readonly IntegrationScopeService $scopes,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<int, Document>  $documents
     */
    public function extractForIntegrationScope(IntegrationScope $scope, array $documents, string $description, User $actor): QuoteSourceExtraction
    {
        $this->assertDocumentsBelongToScope($scope, $documents);

        $extraction = QuoteSourceExtraction::query()->create([
            'client_id' => $scope->client_id,
            'scopeable_type' => $scope->getMorphClass(),
            'scopeable_id' => $scope->getKey(),
            'description_text' => trim($description),
            'description_captured_at' => now(),
            'status' => QuoteSourceExtraction::STATUS_PENDING,
            'created_by_user_id' => $actor->getKey(),
        ]);

        $this->appendDocumentIds($scope, $documents, $actor);
        $this->verifyAndExtract($extraction, $documents, $actor);

        return $extraction->refresh();
    }

    public function retry(QuoteSourceExtraction $extraction, User $actor): QuoteSourceExtraction
    {
        $extraction->loadMissing('documents.document');
        $documents = $extraction->documents
            ->map(fn (QuoteSourceExtractionDocument $item): ?Document => $item->document)
            ->filter()
            ->values()
            ->all();

        if ($documents === []) {
            throw new InvalidArgumentException('An implementation plan is required before extraction can be retried.');
        }

        $extraction->forceFill([
            'status' => QuoteSourceExtraction::STATUS_PENDING,
            'blocked_reason' => null,
            'extracted_rows' => [],
            'confirmed_row_ids' => [],
            'extracted_at' => null,
        ])->save();

        QuoteSourceExtractionDocument::query()
            ->where('quote_source_extraction_id', $extraction->getKey())
            ->delete();

        $this->verifyAndExtract($extraction->refresh(), $documents, $actor);

        return $extraction->refresh();
    }

    /**
     * @param  array<int, string>  $rowIds
     */
    public function confirm(QuoteSourceExtraction $extraction, array $rowIds, User $actor): IntegrationScope
    {
        $scope = $extraction->scopeable;
        if (! $scope instanceof IntegrationScope) {
            throw new InvalidArgumentException('Quote source extraction must belong to an integration scope.');
        }

        if ($extraction->status !== QuoteSourceExtraction::STATUS_EXTRACTED) {
            throw new InvalidArgumentException('Only an extracted implementation plan can be confirmed into the quote scope.');
        }

        $selected = array_fill_keys($rowIds, true);
        $rows = $this->rows($extraction->extracted_rows);
        $confirming = array_values(array_filter($rows, static fn (array $row): bool => isset($selected[$row['id'] ?? ''])
            && ($row['review_status'] ?? 'pending') === 'pending'));

        if ($confirming === []) {
            throw new InvalidArgumentException('Select at least one pending scope row to confirm.');
        }

        [$systems, $tasks, $connections] = $this->applyConfirmedRows($scope, $confirming);
        $confirmedIds = $this->stringRows($extraction->confirmed_row_ids);
        $confirmedIds = array_values(array_unique([...$confirmedIds, ...array_column($confirming, 'id')]));

        foreach ($rows as $index => $row) {
            if (isset($selected[$row['id'] ?? '']) && ($row['review_status'] ?? 'pending') === 'pending') {
                $rows[$index]['review_status'] = 'confirmed';
                $rows[$index]['confirmed_at'] = now()->toIso8601String();
            }
        }

        $this->scopes->update($scope, [
            'systems' => $systems,
            'tasks' => $tasks,
            'connections' => $connections,
            'source_document_ids' => $scope->source_document_ids ?? [],
        ], $actor);

        $extraction->forceFill([
            'extracted_rows' => $rows,
            'confirmed_row_ids' => $confirmedIds,
        ])->save();

        $this->audit->record('quote_source_extraction.rows_confirmed', subject: $extraction, actor: $actor, after: [
            'row_ids' => array_column($confirming, 'id'),
            'integration_scope_id' => $scope->getKey(),
        ]);

        return $scope->refresh();
    }

    /**
     * @param  array<int, string>  $rowIds
     */
    public function reject(QuoteSourceExtraction $extraction, array $rowIds, User $actor): QuoteSourceExtraction
    {
        $selected = array_fill_keys($rowIds, true);
        $rows = $this->rows($extraction->extracted_rows);
        $rejected = [];

        foreach ($rows as $index => $row) {
            if (isset($selected[$row['id'] ?? '']) && ($row['review_status'] ?? 'pending') === 'pending') {
                $rows[$index]['review_status'] = 'rejected';
                $rows[$index]['rejected_at'] = now()->toIso8601String();
                $rejected[] = (string) $row['id'];
            }
        }

        if ($rejected === []) {
            throw new InvalidArgumentException('Select at least one pending scope row to reject.');
        }

        $extraction->forceFill(['extracted_rows' => $rows])->save();
        $this->audit->record('quote_source_extraction.rows_rejected', subject: $extraction, actor: $actor, after: [
            'row_ids' => $rejected,
        ]);

        return $extraction->refresh();
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function verifyAndExtract(QuoteSourceExtraction $extraction, array $documents, User $actor): void
    {
        $documentChunks = [];

        foreach ($documents as $document) {
            $verification = $this->verifier->verify($document, [
                'source' => 'integration_quote_source',
                'claim' => self::QUOTE_CLAIM,
            ]);

            QuoteSourceExtractionDocument::query()->create([
                'quote_source_extraction_id' => $extraction->getKey(),
                'document_id' => $document->getKey(),
                'document_verification_id' => $verification->getKey(),
                'verification_outcome_at_use' => $verification->outcome,
            ]);

            if (! $this->verificationAllowsUse($verification)) {
                $this->block($extraction, $this->verificationBlockedReason($verification), $actor);

                return;
            }

            $chunks = $this->documentText->chunks($document);
            if ($chunks === []) {
                $this->block($extraction, 'No readable text was found in the implementation plan. Upload a searchable PDF, DOCX, XLSX, CSV, or TXT file.', $actor);

                return;
            }

            $documentChunks[] = [
                'id' => $document->getKey(),
                'filename' => $document->original_filename,
                'chunks' => $chunks,
            ];
        }

        $prompt = $this->prompts->envelope(
            id: 'quote_source.integration_extract',
            input: [
                'advisor_description' => $extraction->description_text,
                'documents' => $documentChunks,
                'required_rows' => ['system', 'task', 'connection'],
            ],
            dataQualitySummary: ['verified_documents' => count($documentChunks)],
            sourceReferences: collect($documents)->map(fn (Document $document): string => 'document:'.$document->getKey())->all(),
        );

        $response = $this->ai instanceof QuoteSourceExtractionClient
            ? $this->ai->extractQuoteSource($prompt)
            : $this->ai->analyse($prompt);
        $rows = $this->normaliseRows(Arr::get($response->metadata, 'extracted_rows', []));

        if ($rows === []) {
            $rows = $this->heuristicRows($documentChunks, $extraction->description_text);
        }

        if ($rows === []) {
            $this->block($extraction, 'No clear systems, duplicate-entry tasks, or connections could be extracted. Add an advisor description or upload a more detailed implementation plan.', $actor);

            return;
        }

        $extraction->forceFill([
            'status' => QuoteSourceExtraction::STATUS_EXTRACTED,
            'blocked_reason' => null,
            'extracted_rows' => $rows,
            'extracted_at' => now(),
        ])->save();

        $this->audit->record('quote_source_extraction.prepared', subject: $extraction, actor: $actor, after: [
            'document_ids' => collect($documents)->map(fn (Document $document): string => (string) $document->getKey())->all(),
            'row_count' => count($rows),
            'prompt_version' => $response->promptVersion,
            'prompt_hash' => $response->promptHash,
        ]);
    }

    private function verificationAllowsUse(DocumentVerification $verification): bool
    {
        return $verification->outcome === DocumentVerification::OUTCOME_VERIFIED
            || ($verification->outcome === DocumentVerification::OUTCOME_ADVISORY_FLAG && $verification->resolved_at !== null);
    }

    private function verificationBlockedReason(DocumentVerification $verification): string
    {
        return match ($verification->outcome) {
            DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY => 'This implementation plan has an accuracy discrepancy and must be corrected and re-verified before it can be used in the quote.',
            DocumentVerification::OUTCOME_ADVISORY_FLAG => 'This implementation plan needs an advisor verification resolution before it can be used in the quote.',
            DocumentVerification::OUTCOME_VERIFICATION_ERROR => 'The implementation plan could not be verified. Retry verification before using it in the quote.',
            default => 'The implementation plan is still being verified.',
        };
    }

    private function block(QuoteSourceExtraction $extraction, string $reason, User $actor): void
    {
        $extraction->forceFill([
            'status' => QuoteSourceExtraction::STATUS_BLOCKED,
            'blocked_reason' => $reason,
            'extracted_at' => now(),
        ])->save();

        $this->audit->record('quote_source_extraction.blocked', subject: $extraction, actor: $actor, after: [
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function appendDocumentIds(IntegrationScope $scope, array $documents, User $actor): void
    {
        $ids = array_values(array_unique([
            ...$this->stringRows($scope->source_document_ids),
            ...collect($documents)->map(fn (Document $document): string => (string) $document->getKey())->all(),
        ]));

        $this->scopes->update($scope, ['source_document_ids' => $ids], $actor);
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function assertDocumentsBelongToScope(IntegrationScope $scope, array $documents): void
    {
        if ($documents === []) {
            throw new InvalidArgumentException('Upload at least one implementation plan.');
        }

        foreach ($documents as $document) {
            if (! $document instanceof Document || (string) $document->client_id !== (string) $scope->client_id) {
                throw new InvalidArgumentException('Implementation plan documents must belong to the integration scope client.');
            }

            if ($document->scanner_result !== Document::SCANNER_CLEAN) {
                throw new InvalidArgumentException('The implementation plan must pass malware scanning before it can be used in the quote.');
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>,2:array<int,array<string,mixed>>}
     */
    private function applyConfirmedRows(IntegrationScope $scope, array $rows): array
    {
        $systems = $this->rows($scope->systems);
        $tasks = $this->rows($scope->tasks);
        $connections = $this->rows($scope->connections);
        $systemReferences = [];

        foreach ($systems as $system) {
            foreach ([(string) ($system['id'] ?? ''), (string) ($system['name'] ?? '')] as $reference) {
                if ($reference !== '') {
                    $systemReferences[strtolower($reference)] = (string) $system['id'];
                }
            }
        }

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'system') {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (isset($systemReferences[$key])) {
                continue;
            }

            $id = $this->uniqueId(Str::slug($name) ?: 'system', $systems);
            $system = [
                'id' => $id,
                'name' => $name,
                'vendor' => trim((string) ($row['vendor'] ?? '')) ?: 'Unknown vendor',
                'role' => trim((string) ($row['role'] ?? '')) ?: 'Implementation-plan system',
                'api_quality' => $this->allowed((string) ($row['api_quality'] ?? 'none'), ['rest_public', 'rest_partner', 'webhook', 'csv_export', 'none'], 'none'),
                'auth' => $this->allowed((string) ($row['auth'] ?? 'none'), ['oauth', 'api_key', 'basic', 'none'], 'none'),
                'monthly_records' => max(0, (int) ($row['monthly_records'] ?? 0)),
                'confidence' => $this->allowed((string) ($row['confidence'] ?? 'estimate'), ['known', 'estimate', 'guess'], 'estimate'),
                ...$this->provenance($row),
            ];
            $systems[] = $system;
            $systemReferences[$key] = $id;
            $systemReferences[strtolower($id)] = $id;
        }

        foreach ($rows as $row) {
            if (($row['type'] ?? '') === 'task') {
                $tasks[] = [
                    'id' => $this->uniqueId(Str::slug((string) ($row['description'] ?? 'task')) ?: 'task', $tasks),
                    'description' => trim((string) ($row['description'] ?? '')) ?: 'Implementation-plan duplicate-entry task',
                    'minutes_per_occurrence' => max(0, (float) ($row['minutes_per_occurrence'] ?? 0)),
                    'occurrences_per' => $this->allowed((string) ($row['occurrences_per'] ?? 'month'), ['day', 'week', 'month'], 'month'),
                    'people_count' => max(0, (float) ($row['people_count'] ?? 0)),
                    'hourly_cost' => max(0, (float) ($row['hourly_cost'] ?? 0)),
                    'confidence' => $this->allowed((string) ($row['confidence'] ?? 'estimate'), ['known', 'estimate', 'guess'], 'estimate'),
                    ...$this->provenance($row),
                ];
            }
        }

        foreach ($rows as $row) {
            if (($row['type'] ?? '') !== 'connection') {
                continue;
            }

            $from = $this->systemReference($systemReferences, (string) ($row['from_system'] ?? ''));
            $to = $this->systemReference($systemReferences, (string) ($row['to_system'] ?? ''));
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $connections[] = [
                'id' => $this->uniqueId(Str::slug($from.'-to-'.$to) ?: 'connection', $connections),
                'from_system' => $from,
                'to_system' => $to,
                'direction' => $this->allowed((string) ($row['direction'] ?? 'one_way'), ['one_way', 'two_way'], 'one_way'),
                'transform_complexity' => $this->allowed((string) ($row['transform_complexity'] ?? 'med'), ['low', 'med', 'high'], 'med'),
                'confidence' => $this->allowed((string) ($row['confidence'] ?? 'estimate'), ['known', 'estimate', 'guess'], 'estimate'),
                ...$this->provenance($row),
            ];
        }

        return [$systems, $tasks, $connections];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function provenance(array $row): array
    {
        return [
            'source' => $row['source'] ?? 'document',
            'source_reference' => $row['source_reference'] ?? null,
            'claim' => $row['claim'] ?? null,
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function uniqueId(string $base, array $rows): string
    {
        $existing = collect($rows)->map(fn (array $row): string => (string) ($row['id'] ?? ''))->all();
        $candidate = $base;
        $suffix = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    /** @param array<string,string> $references */
    private function systemReference(array $references, string $value): ?string
    {
        $key = strtolower(trim($value));

        return $key !== '' && isset($references[$key]) ? $references[$key] : null;
    }

    /** @param array<int,string> $allowed */
    private function allowed(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * @param  mixed  $rawRows
     * @return array<int, array<string, mixed>>
     */
    private function normaliseRows(mixed $rawRows): array
    {
        if (! is_array($rawRows)) {
            return [];
        }

        return collect($rawRows)
            ->filter(fn (mixed $row): bool => is_array($row) && in_array($row['type'] ?? null, ['system', 'task', 'connection'], true))
            ->map(function (array $row): array {
                $row['id'] = is_string($row['id'] ?? null) && Str::isUuid($row['id']) ? $row['id'] : (string) Str::uuid();
                $row['review_status'] = 'pending';
                $row['source'] = in_array($row['source'] ?? null, ['document', 'description'], true) ? $row['source'] : 'document';
                $row['source_reference'] = trim((string) ($row['source_reference'] ?? '')) ?: 'quote-source:'.$row['id'];
                $row['claim'] = trim((string) ($row['claim'] ?? '')) ?: 'Extracted implementation-plan scope item';

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id:string,filename:string,chunks:array<int,array{locator:string,text:string}>}>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function heuristicRows(array $documents, string $description): array
    {
        $rows = [];
        foreach ($documents as $document) {
            foreach ($document['chunks'] as $chunk) {
                $rows = [...$rows, ...$this->rowsFromLine($chunk['text'], 'document:'.$document['id'].':'.$chunk['locator'])];
            }
        }
        foreach (preg_split('/\R/u', $description) ?: [] as $index => $line) {
            $rows = [...$rows, ...$this->rowsFromLine($line, 'description:line:'.($index + 1), 'description')];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromLine(string $line, string $reference, string $source = 'document'): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        if (preg_match('/^system\s*:\s*([^;]+)(?:;\s*api\s*:\s*([^;]+))?(?:;\s*auth\s*:\s*([^;]+))?(?:;\s*(?:monthly records|records)\s*:\s*([\d,]+))?\s*$/i', $line, $match)) {
            return [[
                'id' => (string) Str::uuid(), 'type' => 'system', 'name' => trim($match[1]), 'vendor' => trim($match[1]),
                'role' => 'Implementation-plan system', 'api_quality' => $this->normaliseApi($match[2] ?? ''), 'auth' => $this->normaliseAuth($match[3] ?? ''),
                'monthly_records' => (int) str_replace(',', '', $match[4] ?? '0'), 'confidence' => 'estimate', 'review_status' => 'pending',
                'source' => $source, 'source_reference' => $reference, 'claim' => $line,
            ]];
        }

        if (preg_match('/^task\s*:\s*([^;]+);\s*([\d.]+)\s*minutes?;\s*([\d.]+)\s*(?:people|person);\s*(day|week|month)(?:ly)?;\s*\$?([\d,.]+)\s*(?:\/|per\s*)?hour\s*$/i', $line, $match)) {
            return [[
                'id' => (string) Str::uuid(), 'type' => 'task', 'description' => trim($match[1]), 'minutes_per_occurrence' => (float) $match[2],
                'people_count' => (float) $match[3], 'occurrences_per' => strtolower($match[4]), 'hourly_cost' => (float) str_replace(',', '', $match[5]),
                'confidence' => 'estimate', 'review_status' => 'pending', 'source' => $source, 'source_reference' => $reference, 'claim' => $line,
            ]];
        }

        if (preg_match('/^connection\s*:\s*([^\-;]+?)\s*(?:->|to)\s*([^;]+?)(?:;\s*(one[\s_-]*way|two[\s_-]*way))?(?:;\s*(low|med(?:ium)?|high))?\s*$/i', $line, $match)) {
            return [[
                'id' => (string) Str::uuid(), 'type' => 'connection', 'from_system' => trim($match[1]), 'to_system' => trim($match[2]),
                'direction' => str_contains(strtolower($match[3] ?? ''), 'two') ? 'two_way' : 'one_way',
                'transform_complexity' => str_starts_with(strtolower($match[4] ?? ''), 'high') ? 'high' : (str_starts_with(strtolower($match[4] ?? ''), 'low') ? 'low' : 'med'),
                'confidence' => 'estimate', 'review_status' => 'pending', 'source' => $source, 'source_reference' => $reference, 'claim' => $line,
            ]];
        }

        return [];
    }

    private function normaliseApi(string $value): string
    {
        $value = strtolower(str_replace([' ', '-'], '_', trim($value)));

        return $this->allowed($value, ['rest_public', 'rest_partner', 'webhook', 'csv_export', 'none'], 'none');
    }

    private function normaliseAuth(string $value): string
    {
        $value = strtolower(str_replace([' ', '-'], '_', trim($value)));

        return $this->allowed($value, ['oauth', 'api_key', 'basic', 'none'], 'none');
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->values()
            ->all();
    }

    /** @return array<int,string> */
    private function stringRows(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(static fn (mixed $row): bool => is_string($row))
            ->map(fn (string $row): string => $row)
            ->values()
            ->all();
    }
}
