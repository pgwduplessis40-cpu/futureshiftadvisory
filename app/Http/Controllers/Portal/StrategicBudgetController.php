<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Budgets\DdPlanBudgetAccess;
use App\Services\Budgets\StrategicBudgetExcelExporter;
use App\Services\Budgets\StrategicBudgetPdfDocument;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Pdf\ResilientPdfPreviewRenderer;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\ServiceWorkspaces;
use App\Services\ServiceActivations\ServiceActivationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class StrategicBudgetController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly StrategicBudgetService $budgets,
        private readonly StrategicBudgetExcelExporter $exporter,
        private readonly StrategicBudgetPdfDocument $pdfDocument,
        private readonly ResilientPdfPreviewRenderer $pdf,
        private readonly DdPlanBudgetAccess $ddPlanBudgetAccess,
        private readonly ServiceActivationManager $activations,
        private readonly ServiceWorkspaces $workspaces,
    ) {}

    public function show(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);
        $access = $this->ddPlanBudgetAccess->payload($client);

        if ($access['allowed'] !== true) {
            return $this->quoteApprovalResponse($client, $access);
        }

        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));

        return Inertia::render('portal/StrategicPlanBudget', [
            'client' => $this->clientPayload($client),
            'budget' => $this->budgets->portalPayload($budget),
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'onboardingUrl' => route('portal.onboarding.step', ['step' => 'documents'], absolute: false),
            'dashboardUrl' => route('portal.dashboard', absolute: false),
            'pdfUrl' => route('portal.business-plan-budget.pdf', absolute: false),
            'businessPlanPdfUrl' => route('portal.business-plan-budget.business-plan.pdf', absolute: false),
            'budgetPdfUrl' => route('portal.business-plan-budget.budget-pack.pdf', absolute: false),
            'workspaces' => $this->workspaces->payload($client, ServiceWorkspaces::KEY_DD_PLAN_BUDGET),
        ]);
    }

    public function document(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));

        return Inertia::render('portal/StrategicPlanBudgetDocument', [
            'client' => $this->clientPayload($client),
            'budget' => $this->budgets->portalPayload($budget),
            'workspaceUrl' => route('portal.business-plan-budget.show', absolute: false),
            'pdfUrl' => route('portal.business-plan-budget.pdf', absolute: false),
            'preparedAt' => now()->toIso8601String(),
        ]);
    }

    public function pdf(Request $request): SymfonyResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));
        $payload = $this->budgets->portalPayload($budget);
        $contents = $this->pdf->render(
            $this->pdfDocument->html($client, $payload),
            'Business plan and budget - '.($client->trading_name ?: $client->legal_name),
        );
        $filename = Str::slug($client->trading_name ?: $client->legal_name).'-'.Str::slug((string) ($payload['label'] ?? 'plan-budget')).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function businessPlanPdf(Request $request): SymfonyResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));
        $payload = $this->budgets->portalPayload($budget);
        $businessName = $client->trading_name ?: $client->legal_name;
        $contents = $this->pdf->render(
            $this->pdfDocument->businessPlanHtml($client, $payload),
            'Business plan - '.$businessName,
        );
        $filename = Str::slug($businessName).'-business-plan.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function budgetPackPdf(Request $request): SymfonyResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));
        $payload = $this->budgets->portalPayload($budget);
        $businessName = $client->trading_name ?: $client->legal_name;
        $contents = $this->pdf->render(
            $this->pdfDocument->budgetPackHtml($client, $payload),
            'Budget pack - '.$businessName,
        );
        $filename = Str::slug($businessName).'-budget-pack.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));

        $this->budgets->update($budget, $this->validatedBudget($request), $user);

        return to_route('portal.business-plan-budget.show')->with('status', 'business-plan-budget-saved');
    }

    public function submit(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client));
        if (! $budget->isUnlocked()) {
            return to_route('portal.business-plan-budget.show')
                ->with('status', 'business-plan-budget-locked')
                ->with('business_plan_budget_error', 'Upload a P&L or management accounts file before submitting the combined plan and budget.');
        }
        if (($this->budgets->portalPayload($budget)['business_plan_ready'] ?? false) !== true) {
            return to_route('portal.business-plan-budget.show')
                ->with('status', 'business-plan-incomplete')
                ->with('business_plan_budget_error', 'Complete every plan section before submitting for advisor approval.');
        }

        $this->budgets->submit($budget, $user);

        return to_route('portal.business-plan-budget.show')->with('status', 'business-plan-budget-submitted');
    }

    public function export(Request $request): SymfonyResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertPlanBudgetAccess($client);
        $budget = $this->budgets->ensureForClient($client, $this->latestDueDiligencePlan($client))->load('client');
        $contents = $this->exporter->export($budget);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->exporter->filename($budget).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function requestQuote(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate([
            'confirm_quote_request' => ['accepted'],
        ]);

        $access = $this->ddPlanBudgetAccess->payload($client);
        if ($access['allowed'] === true) {
            return to_route('portal.business-plan-budget.show');
        }

        $openRequest = $this->ddPlanBudgetAccess->openRequest($client);
        if ($openRequest instanceof ServiceActivation) {
            return to_route('portal.service-activations.show', $openRequest)
                ->with('status', 'dd-plan-budget-quote-already-requested');
        }

        $intake = $this->quoteRequestIntake($client);
        $activation = $this->activations->request(
            client: $client,
            actor: $user,
            serviceType: ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            intake: $intake,
            pricingPreview: $this->activations->pricingPreviewForRequest(
                ServiceActivation::SERVICE_DD_PLAN_BUDGET,
                $intake,
                client: $client,
            ),
        );

        return to_route('portal.service-activations.show', $activation)
            ->with('status', 'dd-plan-budget-quote-requested');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBudget(Request $request): array
    {
        return $request->validate([
            'business_plan_sections' => ['array', 'max:12'],
            'business_plan_sections.*.key' => ['required_with:business_plan_sections', 'string', 'max:80'],
            'business_plan_sections.*.answer' => ['nullable', 'string', 'max:6000'],
            'horizon_months' => ['required', 'integer', Rule::in([12, 24, 36])],
            'expected_runway_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'assumptions' => ['array'],
            'assumptions.opening_cash_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'assumptions.revenue_growth_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'assumptions.cost_inflation_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_before_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assumptions.target_net_profit_after_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'implementation_costs' => ['array', 'max:50'],
            'implementation_costs.*.label' => ['nullable', 'string', 'max:180'],
            'implementation_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'implementation_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'implementation_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'monthly_fixed_costs' => ['array', 'max:50'],
            'monthly_fixed_costs.*.label' => ['nullable', 'string', 'max:180'],
            'monthly_fixed_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'monthly_fixed_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'monthly_fixed_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'revenue_forecast' => ['array', 'max:50'],
            'revenue_forecast.*.label' => ['nullable', 'string', 'max:180'],
            'revenue_forecast.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'revenue_forecast.*.month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'revenue_forecast.*.monthly_growth_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'revenue_forecast.*.variable_cost_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'revenue_forecast.*.gross_profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_forecast.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_sources' => ['array', 'max:50'],
            'funding_sources.*.label' => ['nullable', 'string', 'max:180'],
            'funding_sources.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_sources.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'funding_sources.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'future_costs' => ['array', 'max:50'],
            'future_costs.*.label' => ['nullable', 'string', 'max:180'],
            'future_costs.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'future_costs.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'future_costs.*.year' => ['nullable', 'integer', 'min:2', 'max:5'],
            'future_costs.*.recurring' => ['nullable', 'boolean'],
            'future_costs.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
            'funding_scenarios' => ['array', 'max:10'],
            'funding_scenarios.*.name' => ['nullable', 'string', 'max:180'],
            'funding_scenarios.*.type' => ['nullable', 'string', Rule::in(['bank_loan', 'investor', 'mixed'])],
            'funding_scenarios.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'funding_scenarios.*.year' => ['nullable', 'integer', 'min:1', 'max:5'],
            'funding_scenarios.*.interest_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.term_years' => ['nullable', 'integer', 'min:0', 'max:30'],
            'funding_scenarios.*.interest_only_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'funding_scenarios.*.investor_equity_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_scenarios.*.confidence' => ['nullable', 'string', Rule::in(['known', 'estimate', 'guess'])],
        ]);
    }

    private function latestDueDiligencePlan(Client $client): ?BusinessPlan
    {
        $engagement = $this->latestDueDiligenceEngagement($client);

        if (! $engagement instanceof DdEngagement) {
            return null;
        }

        return BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->latest()
            ->first();
    }

    private function latestDueDiligenceEngagement(Client $client): ?DdEngagement
    {
        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    /**
     * @return array<string, string|null>
     */
    private function clientPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $client->engagement_type instanceof EngagementType
                ? $client->engagement_type->value
                : (string) $client->engagement_type,
            'engagement_type_label' => $client->engagement_type instanceof EngagementType
                ? $client->engagement_type->label()
                : str((string) $client->engagement_type)->replace('_', ' ')->title()->toString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $access
     */
    private function quoteApprovalResponse(Client $client, array $access): Response
    {
        $engagement = $this->latestDueDiligenceEngagement($client);

        return Inertia::render('portal/StrategicPlanBudgetQuoteApproval', [
            'client' => $this->clientPayload($client),
            'access' => $access,
            'target' => [
                'name' => $engagement?->target_name,
                'vendor_name' => data_get($engagement?->target_details, 'vendor_name'),
                'industry' => data_get($engagement?->target_details, 'industry'),
                'asking_price' => data_get($engagement?->target_details, 'asking_price'),
            ],
            'dashboardUrl' => route('portal.dashboard', absolute: false),
            'ddWorkspaceUrl' => route('portal.dd-plan.show', absolute: false),
            'requestQuoteUrl' => route('portal.business-plan-budget.quote.store', absolute: false),
            'workspaces' => $this->workspaces->payload($client, ServiceWorkspaces::KEY_DUE_DILIGENCE),
        ]);
    }

    private function assertPlanBudgetAccess(Client $client): void
    {
        abort_unless(
            $this->ddPlanBudgetAccess->allowed($client),
            403,
            'Business Plan & Budget requires FSA quote approval for this DD client.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteRequestIntake(Client $client): array
    {
        $engagement = $this->latestDueDiligenceEngagement($client);
        $capability = (array) data_get($engagement?->target_details, 'client_capability', []);

        return [
            'target_name' => $engagement?->target_name,
            'vendor_name' => data_get($engagement?->target_details, 'vendor_name'),
            'industry' => data_get($engagement?->target_details, 'industry'),
            'asking_price' => data_get($engagement?->target_details, 'asking_price'),
            'capability_mode' => data_get($capability, 'mode'),
            'support_level' => data_get($capability, 'support_level'),
            'dd_experience' => data_get($capability, 'dd_experience'),
            'business_ownership_experience' => data_get($capability, 'business_ownership_experience'),
            'financial_confidence' => data_get($capability, 'financial_confidence'),
            'preferred_guidance' => data_get($capability, 'preferred_guidance'),
            'timing' => 'Client requested FSA quote approval for the DD + Business Plan & Budget add-on.',
            'notes' => 'Quote request created from the inactive DD Business Plan & Budget module. Use the DD onboarding support profile to select the right support level, scope, and service fee.',
        ];
    }
}
