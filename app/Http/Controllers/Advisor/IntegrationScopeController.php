<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\FeeMethod;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\IntegrationScope;
use App\Models\QuoteSourceExtraction;
use App\Models\QuoteSourceExtractionDocument;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\FeeCalculator;
use App\Services\Integrations\IntegrationScopeService;
use App\Services\QuoteSources\QuoteSourceExtractor;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\Exceptions\SecureFileStorageException;
use App\Services\Storage\SecureFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IntegrationScopeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $clientIds = $user->user_type === User::TYPE_SUPER_ADMIN
            ? null
            : $user->accessibleClientIds();

        $clientQuery = Client::query()->orderBy('legal_name');
        if ($clientIds !== null) {
            $clientQuery->whereIn('id', $clientIds);
        }

        $scopeQuery = IntegrationScope::query()
            ->with('client')
            ->latest();

        if ($clientIds !== null) {
            $scopeQuery->whereIn('client_id', $clientIds);
        }

        $scopes = $scopeQuery
            ->get()
            ->map(fn (IntegrationScope $scope): array => [
                'id' => $scope->getKey(),
                'client_name' => $scope->client?->legal_name,
                'status' => $scope->status,
                'delivery_mode' => $scope->delivery_mode,
                'complexity_band' => data_get($scope->computed, 'complexity_band'),
                'annual_savings' => data_get($scope->computed, 'annual_savings'),
                'quoted_fee' => data_get($scope->computed, 'quoted_fee'),
                'url' => route('advisor.integration-scopes.show', $scope, absolute: false),
            ])
            ->all();

        return Inertia::render('advisor/integration-scopes/Index', [
            'scopes' => $scopes,
            'clients' => $clientQuery
                ->limit(100)
                ->get()
                ->map(fn (Client $client): array => [
                    'id' => $client->getKey(),
                    'name' => $client->legal_name ?: $client->trading_name ?: 'Unnamed client',
                    'store_url' => route('advisor.clients.integration-scopes.store', $client, absolute: false),
                ])
                ->values(),
        ]);
    }

    public function show(Request $request, IntegrationScope $integrationScope): Response
    {
        $integrationScope->loadMissing([
            'client',
            'pvCalculation',
            'feeCalculations',
            'quoteSourceExtractions.documents.document',
            'quoteSourceExtractions.documents.verification',
        ]);
        Gate::authorize('view', $integrationScope->client);

        return Inertia::render('advisor/integration-scopes/Show', [
            'scope' => $this->payload($integrationScope),
            'urls' => [
                'index' => route('advisor.integration-scopes.index', absolute: false),
                'update' => route('advisor.integration-scopes.update', $integrationScope, absolute: false),
                'recalculate' => route('advisor.integration-scopes.recalculate', $integrationScope, absolute: false),
                'feeCalculation' => route('advisor.integration-scopes.fee-calculations.store', $integrationScope, absolute: false),
                'quoteSourceExtraction' => route('advisor.integration-scopes.quote-source-extractions.store', $integrationScope, absolute: false),
                'clientProposals' => route('advisor.clients.show', $integrationScope->client, absolute: false).'#section-proposals',
            ],
        ]);
    }

    public function store(Request $request, Client $client, IntegrationScopeService $scopes): RedirectResponse
    {
        Gate::authorize('view', $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $attributes = $request->boolean('sample') ? $this->sampleScope() : [];
        $scope = $scopes->create($client, [...$attributes, ...$this->validatedScope($request)], $user);

        return to_route('advisor.integration-scopes.show', $scope)->with('status', 'integration-scope-created');
    }

    public function update(Request $request, IntegrationScope $integrationScope, IntegrationScopeService $scopes): RedirectResponse
    {
        $integrationScope->loadMissing('client');
        Gate::authorize('view', $integrationScope->client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $scopes->update($integrationScope, $this->validatedScope($request), $user);

        return back()->with('status', 'integration-scope-updated');
    }

    public function recalculate(Request $request, IntegrationScope $integrationScope, IntegrationScopeService $scopes): RedirectResponse
    {
        $integrationScope->loadMissing('client');
        Gate::authorize('view', $integrationScope->client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $scopes->recalculate($integrationScope, $user);

        return back()->with('status', 'integration-scope-recalculated');
    }

    public function createFeeCalculation(Request $request, IntegrationScope $integrationScope, FeeCalculator $fees): RedirectResponse
    {
        $integrationScope->loadMissing('client');
        Gate::authorize('view', $integrationScope->client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $fees->calculate($integrationScope->client, FeeMethod::Integration, [
                'integration_scope_id' => $integrationScope->getKey(),
            ], [
                'created_by_user_id' => $user->getKey(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['fee_calculation' => $exception->getMessage()]);
        }

        return back()->with('status', 'integration-fee-calculation-created');
    }

    public function extractQuoteSources(
        Request $request,
        IntegrationScope $integrationScope,
        SecureFileWriter $files,
        QuoteSourceExtractor $extractor,
    ): RedirectResponse {
        $integrationScope->loadMissing('client');
        Gate::authorize('view', $integrationScope->client);
        Gate::authorize('create', Document::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:4000'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,csv,txt', 'max:20480'],
        ]);

        try {
            $documents = collect($request->file('documents', []))
                ->map(fn ($file): Document => $files->write(
                    uploadedFile: $file,
                    owner: $user,
                    category: Document::CATEGORY_PLAN_ATTACHMENT,
                    clientId: (string) $integrationScope->client_id,
                ))
                ->all();
            $extractor->extractForIntegrationScope(
                $integrationScope,
                $documents,
                (string) ($validated['description'] ?? ''),
                $user,
            );
        } catch (InfectedFileException) {
            return back()->withErrors(['documents' => 'The implementation plan could not be accepted because the file scanner detected a threat.']);
        } catch (SecureFileStorageException|InvalidArgumentException $exception) {
            return back()->withErrors(['documents' => $exception->getMessage()]);
        }

        return back()->with('status', 'integration-quote-source-prepared');
    }

    public function retryQuoteSourceExtraction(
        Request $request,
        IntegrationScope $integrationScope,
        QuoteSourceExtraction $quoteSourceExtraction,
        QuoteSourceExtractor $extractor,
    ): RedirectResponse {
        $this->authorizeExtraction($integrationScope, $quoteSourceExtraction);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $extractor->retry($quoteSourceExtraction, $user);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['quote_source' => $exception->getMessage()]);
        }

        return back()->with('status', 'integration-quote-source-retried');
    }

    public function confirmQuoteSourceRows(
        Request $request,
        IntegrationScope $integrationScope,
        QuoteSourceExtraction $quoteSourceExtraction,
        QuoteSourceExtractor $extractor,
    ): RedirectResponse {
        $this->authorizeExtraction($integrationScope, $quoteSourceExtraction);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validate(['row_ids' => ['required', 'array', 'min:1'], 'row_ids.*' => ['required', 'uuid']]);

        try {
            $extractor->confirm($quoteSourceExtraction, $validated['row_ids'], $user);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['quote_source' => $exception->getMessage()]);
        }

        return back()->with('status', 'integration-quote-source-rows-confirmed');
    }

    public function rejectQuoteSourceRows(
        Request $request,
        IntegrationScope $integrationScope,
        QuoteSourceExtraction $quoteSourceExtraction,
        QuoteSourceExtractor $extractor,
    ): RedirectResponse {
        $this->authorizeExtraction($integrationScope, $quoteSourceExtraction);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validate(['row_ids' => ['required', 'array', 'min:1'], 'row_ids.*' => ['required', 'uuid']]);

        try {
            $extractor->reject($quoteSourceExtraction, $validated['row_ids'], $user);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['quote_source' => $exception->getMessage()]);
        }

        return back()->with('status', 'integration-quote-source-rows-rejected');
    }

    public function showQuoteSourceDocument(
        Request $request,
        IntegrationScope $integrationScope,
        string $document,
        AuditWriter $audit,
    ): StreamedResponse {
        $integrationScope->loadMissing('client');
        Gate::authorize('view', $integrationScope->client);

        $link = QuoteSourceExtractionDocument::query()
            ->with('document')
            ->where('document_id', $document)
            ->whereHas('extraction', fn ($query) => $query
                ->where('scopeable_type', $integrationScope->getMorphClass())
                ->where('scopeable_id', $integrationScope->getKey()))
            ->firstOrFail();
        $source = $link->document;
        abort_unless($source instanceof Document && $source->scanner_result === Document::SCANNER_CLEAN, 404);

        $audit->recordRead('quote_source_document.viewed', $source, [
            'integration_scope_id' => $integrationScope->getKey(),
        ]);

        return Storage::disk('secure_local')->response(
            $source->stored_path,
            $source->original_filename,
            ['Content-Type' => $source->mime_type ?: 'application/octet-stream'],
            'inline',
        );
    }

    /** @return array<string, mixed> */
    private function validatedScope(Request $request): array
    {
        return $request->validate([
            'systems' => ['sometimes', 'array'],
            'tasks' => ['sometimes', 'array'],
            'connections' => ['sometimes', 'array'],
            'delivery_mode' => ['nullable', 'in:inhouse,partner,lowcode,mixed'],
            'partner_cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'partner_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capture_percent' => ['nullable', 'numeric', 'min:50', 'max:95'],
            'savings_horizon_years' => ['nullable', 'integer', 'min:1', 'max:5'],
            'discount_rate_percent' => ['nullable', 'numeric', 'min:0.01', 'max:99.99'],
            'quoted_fee' => ['nullable', 'numeric', 'min:0'],
            'fsa_hosting_enabled' => ['nullable', 'boolean'],
            'fee_override_reason' => ['nullable', 'string', 'max:1200'],
            'source_document_ids' => ['sometimes', 'array'],
            'extracted_rows' => ['sometimes', 'array'],
        ]);
    }

    /** @return array<string, mixed> */
    private function sampleScope(): array
    {
        return [
            'systems' => [
                ['id' => 'xero', 'name' => 'Xero', 'vendor' => 'Xero', 'role' => 'Accounting and invoice ledger', 'api_quality' => 'rest_public', 'auth' => 'oauth', 'monthly_records' => 2800, 'confidence' => 'known', 'source' => 'manual'],
                ['id' => 'field-service', 'name' => 'Field Service Board', 'vendor' => 'Legacy vendor', 'role' => 'Job completion and time capture', 'api_quality' => 'none', 'auth' => 'none', 'monthly_records' => 12500, 'confidence' => 'estimate', 'source' => 'manual'],
                ['id' => 'crm', 'name' => 'Client CRM', 'vendor' => 'CRM vendor', 'role' => 'Customer and sales record', 'api_quality' => 'rest_partner', 'auth' => 'oauth', 'monthly_records' => 8400, 'confidence' => 'estimate', 'source' => 'manual'],
            ],
            'tasks' => [
                ['id' => 'invoice-rekeying', 'description' => 'Re-key completed field jobs into Xero invoices', 'minutes_per_occurrence' => 14, 'occurrences_per' => 'day', 'people_count' => 2, 'hourly_cost' => 52, 'confidence' => 'known', 'source' => 'manual'],
                ['id' => 'crm-status', 'description' => 'Re-key job progress into the CRM', 'minutes_per_occurrence' => 9, 'occurrences_per' => 'day', 'people_count' => 2, 'hourly_cost' => 48, 'confidence' => 'estimate', 'source' => 'manual'],
            ],
            'connections' => [
                ['id' => 'field-to-xero', 'from_system' => 'field-service', 'to_system' => 'xero', 'direction' => 'one_way', 'transform_complexity' => 'med', 'confidence' => 'estimate', 'source' => 'manual'],
                ['id' => 'crm-to-field', 'from_system' => 'crm', 'to_system' => 'field-service', 'direction' => 'two_way', 'transform_complexity' => 'high', 'confidence' => 'estimate', 'source' => 'manual'],
            ],
            'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
            'capture_percent' => 80,
            'savings_horizon_years' => 3,
            'discount_rate_percent' => 12,
            'fsa_hosting_enabled' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(IntegrationScope $scope): array
    {
        $computed = $scope->computed ?? [];
        if (is_array($computed['hosting'] ?? null)) {
            $computed['hosting'] = Arr::only($computed['hosting'], [
                'enabled',
                'monthly_fee',
                'annual_fee',
                'currency',
            ]);
        }

        return [
            'id' => $scope->getKey(),
            'client_id' => $scope->client_id,
            'client_name' => $scope->client?->legal_name,
            'status' => $scope->status,
            'delivery_mode' => $scope->delivery_mode,
            'fsa_hosting_enabled' => $scope->fsa_hosting_enabled,
            'systems' => $scope->systems ?? [],
            'tasks' => $scope->tasks ?? [],
            'connections' => $scope->connections ?? [],
            'computed' => $computed,
            'flags' => $scope->flags ?? [],
            'pv_calculation_id' => $scope->pv_calculation_id,
            'fee_calculations' => $scope->feeCalculations
                ->sortByDesc('created_at')
                ->map(fn ($calculation): array => [
                    'id' => $calculation->getKey(),
                    'suggested_low' => $calculation->suggested_low,
                    'suggested_mid' => $calculation->suggested_mid,
                    'suggested_high' => $calculation->suggested_high,
                    'roi_ratio' => $calculation->roi_ratio,
                    'created_at' => $calculation->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'quote_source_extractions' => $scope->quoteSourceExtractions
                ->sortByDesc('created_at')
                ->map(fn (QuoteSourceExtraction $extraction): array => [
                    'id' => $extraction->getKey(),
                    'status' => $extraction->status,
                    'description' => $extraction->description_text,
                    'blocked_reason' => $extraction->blocked_reason,
                    'rows' => $extraction->extracted_rows ?? [],
                    'documents' => $extraction->documents
                        ->map(fn (QuoteSourceExtractionDocument $item): array => [
                            'id' => $item->document?->getKey(),
                            'filename' => $item->document?->original_filename,
                            'verification_outcome' => $item->verification_outcome_at_use,
                            'url' => $item->document
                                ? route('advisor.integration-scopes.quote-source-documents.show', [$scope, $item->document], absolute: false)
                                : null,
                        ])
                        ->values()
                        ->all(),
                    'confirm_url' => route('advisor.integration-scopes.quote-source-extractions.confirm', [$scope, $extraction], absolute: false),
                    'reject_url' => route('advisor.integration-scopes.quote-source-extractions.reject', [$scope, $extraction], absolute: false),
                    'retry_url' => route('advisor.integration-scopes.quote-source-extractions.retry', [$scope, $extraction], absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    private function authorizeExtraction(IntegrationScope $scope, QuoteSourceExtraction $extraction): void
    {
        $scope->loadMissing('client');
        Gate::authorize('view', $scope->client);
        abort_unless(
            $extraction->scopeable_type === $scope->getMorphClass()
            && (string) $extraction->scopeable_id === (string) $scope->getKey(),
            404,
        );
    }
}
