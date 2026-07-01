<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Enums\EngagementType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\EconomicIndicator;
use App\Models\Proposal;
use App\Models\StrategicBudget;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\BudgetCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class StrategicBudgetService
{
    private const PLAN_SECTION_KEYS = [
        'goals',
        'current_position',
        'market_customers',
        'operations',
        'risks',
        'swot',
        'action_priorities',
        'evidence_documents',
    ];

    private const FINANCIAL_KEYWORDS = [
        'p&l',
        'p and l',
        'profit and loss',
        'profit-loss',
        'profit_loss',
        'management accounts',
        'management_accounts',
        'management-account',
    ];

    public function __construct(
        private readonly BudgetCalculator $calculator,
        private readonly AuditWriter $audit,
    ) {}

    public function ensureForClient(Client $client, ?BusinessPlan $plan = null): StrategicBudget
    {
        $pathway = $this->pathway($client);
        $financials = $this->financialDocuments($client);
        $unlocked = $financials->isNotEmpty();
        $budget = StrategicBudget::query()->firstOrNew([
            'client_id' => $client->getKey(),
            'pathway' => $pathway,
        ]);
        $existingStatus = (string) ($budget->status ?: StrategicBudget::STATUS_LOCKED);

        if (! $budget->exists) {
            $budget->forceFill([
                'label' => $this->label($pathway),
                'status' => $unlocked ? StrategicBudget::STATUS_SYSTEM_DRAFT : StrategicBudget::STATUS_LOCKED,
                'horizon_months' => $this->defaultHorizonMonths($client),
                'source_financials' => $this->sourceFinancialsPayload($financials),
                'client_goals' => $this->clientGoals($client),
                'advisor_goals' => [],
                'business_plan_sections' => [],
                'business_plan_source_drafts' => [],
                'business_plan_prompts' => [],
                'assumptions' => [],
                'implementation_costs' => [],
                'monthly_fixed_costs' => [],
                'future_costs' => [],
                'revenue_forecast' => [],
                'funding_sources' => [],
                'funding_scenarios' => [],
                'computed' => [],
                'flags' => [],
                'confidence' => [],
            ]);
        }

        $status = $existingStatus;
        if ($unlocked && $existingStatus === StrategicBudget::STATUS_LOCKED) {
            $status = StrategicBudget::STATUS_SYSTEM_DRAFT;
        }
        if (! $unlocked) {
            $status = StrategicBudget::STATUS_LOCKED;
        }

        $budget->forceFill([
            'business_plan_id' => $plan?->getKey() ?? $budget->business_plan_id,
            'label' => $this->label($pathway),
            'status' => $status,
            'horizon_months' => (int) ($budget->horizon_months ?: $this->defaultHorizonMonths($client)),
            'source_financials' => $this->sourceFinancialsPayload($financials),
            'client_goals' => $this->clientGoals($client),
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_prompts' => $this->businessPlanPrompts($pathway),
            'business_plan_source_drafts' => $this->sourceDrafts($client, $plan, $pathway),
            'business_plan_sections' => $this->normaliseBusinessPlanSections(
                (array) ($budget->business_plan_sections ?? []),
                $pathway,
            ),
        ])->save();

        $budget = $budget->refresh();

        if ($budget->isUnlocked() && ($budget->computed ?? []) === []) {
            $this->recompute($budget);
        } else {
            $this->refreshReadiness($budget);
        }

        return $budget->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(StrategicBudget $budget, array $input, User $actor): StrategicBudget
    {
        return DB::transaction(function () use ($budget, $input, $actor): StrategicBudget {
            $status = in_array($budget->status, [
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true)
                ? StrategicBudget::STATUS_CLIENT_WORKING_DRAFT
                : (string) ($budget->status ?: StrategicBudget::STATUS_CLIENT_WORKING_DRAFT);

            if ($status === StrategicBudget::STATUS_SYSTEM_DRAFT) {
                $status = StrategicBudget::STATUS_CLIENT_WORKING_DRAFT;
            }

            $updates = [
                'status' => $status,
                'business_plan_sections' => $this->normaliseBusinessPlanSections(
                    (array) ($input['business_plan_sections'] ?? $budget->business_plan_sections ?? []),
                    (string) $budget->pathway,
                ),
                'business_plan_prompts' => $this->businessPlanPrompts((string) $budget->pathway),
                'business_plan_submitted_at' => null,
                'business_plan_approved_at' => null,
                'business_plan_approved_by_user_id' => null,
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
            ];

            if ($budget->isUnlocked()) {
                $updates = [
                    ...$updates,
                    'horizon_months' => $this->horizonMonths($input['horizon_months'] ?? $budget->horizon_months),
                    'expected_runway_months' => $this->expectedRunway($input['expected_runway_months'] ?? null),
                    'assumptions' => (array) ($input['assumptions'] ?? []),
                    'implementation_costs' => $this->calculator->normaliseRows((array) ($input['implementation_costs'] ?? [])),
                    'monthly_fixed_costs' => $this->calculator->normaliseRows((array) ($input['monthly_fixed_costs'] ?? [])),
                    'future_costs' => $this->calculator->normaliseFutureCosts((array) ($input['future_costs'] ?? [])),
                    'revenue_forecast' => $this->calculator->normaliseRows((array) ($input['revenue_forecast'] ?? [])),
                    'funding_sources' => $this->calculator->normaliseRows((array) ($input['funding_sources'] ?? [])),
                    'funding_scenarios' => $this->calculator->normaliseFundingScenarios((array) ($input['funding_scenarios'] ?? [])),
                ];
            }

            $budget->forceFill($updates)->save();

            $budget = $budget->isUnlocked()
                ? $this->recompute($budget->refresh())
                : $this->refreshReadiness($budget->refresh());

            $this->audit->record('strategic_budget.updated', subject: $budget, actor: $actor, after: [
                'client_id' => $budget->client_id,
                'pathway' => $budget->pathway,
                'status' => $budget->status,
                'horizon_months' => $budget->horizon_months,
                'confidence_score' => data_get($budget->confidence, 'score'),
            ]);

            return $budget->refresh();
        });
    }

    public function submit(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);

        $budget = $this->recompute($budget);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            'submitted_at' => now(),
            'business_plan_submitted_at' => now(),
            'approved_at' => null,
            'approved_by_user_id' => null,
            'business_plan_approved_at' => null,
            'business_plan_approved_by_user_id' => null,
        ])->save();

        $this->audit->record('strategic_budget.submitted', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
        ]);

        return $budget->refresh();
    }

    public function approve(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);

        $budget = $this->recompute($budget);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_ADVISOR_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $actor->getKey(),
            'business_plan_approved_at' => now(),
            'business_plan_approved_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('strategic_budget.approved', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
        ]);

        return $budget->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    public function updateAdvisorGoals(StrategicBudget $budget, array $goals, User $actor): StrategicBudget
    {
        $normalised = collect($goals)
            ->filter(fn (mixed $goal): bool => is_array($goal))
            ->map(fn (array $goal): array => [
                'title' => trim((string) ($goal['title'] ?? '')),
                'measure' => trim((string) ($goal['measure'] ?? '')),
            ])
            ->filter(fn (array $goal): bool => $goal['title'] !== '' || $goal['measure'] !== '')
            ->values()
            ->all();

        $budget->forceFill(['advisor_goals' => $normalised])->save();
        $this->audit->record('strategic_budget.advisor_goals_updated', subject: $budget, actor: $actor, after: [
            'goal_count' => count($normalised),
        ]);

        return $this->refreshReadiness($budget->refresh());
    }

    public function markUsedInProposal(StrategicBudget $budget, Proposal $proposal, User $actor): StrategicBudget
    {
        if (! $budget->isApprovedForProposal()) {
            return $budget->refresh();
        }

        $budget->forceFill([
            'status' => StrategicBudget::STATUS_USED_IN_PROPOSAL,
            'proposal_id' => $proposal->getKey(),
            'used_in_proposal_at' => now(),
        ])->save();

        $this->audit->record('strategic_budget.used_in_proposal', subject: $budget, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
        ]);

        return $budget->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPayload(StrategicBudget $budget): array
    {
        return [
            ...$this->basePayload($budget),
            'update_url' => route('portal.business-plan-budget.update', absolute: false),
            'submit_url' => route('portal.business-plan-budget.submit', absolute: false),
            'budget_pack_available' => $budget->accepted_snapshot_at !== null,
            'budget_pack_locked_reason' => $budget->accepted_snapshot_at === null
                ? 'Budget Pack PDF unlocks automatically after the proposal is accepted.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function advisorPayload(StrategicBudget $budget): array
    {
        return [
            ...$this->basePayload($budget),
            'approve_url' => route('advisor.clients.strategic-budget.approve', $budget->client_id, absolute: false),
            'advisor_goals_url' => route('advisor.clients.strategic-budget.advisor-goals', $budget->client_id, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalGuardPayload(StrategicBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'approved' => $budget->isApprovedForProposal(),
            'confidence_score' => (int) data_get($budget->confidence, 'score', 0),
            'warning' => $budget->isApprovedForProposal()
                ? null
                : $budget->label.' has not been advisor-approved. Generating a proposal now requires a hard acknowledgement override.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(StrategicBudget $budget): array
    {
        $computed = (array) ($budget->computed ?? []);
        $confidence = (array) ($budget->confidence ?? []);

        return [
            'id' => $budget->id,
            'label' => $budget->label,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'locked' => ! $budget->isUnlocked(),
            'horizon_months' => $budget->horizon_months,
            'expected_runway_months' => $budget->expected_runway_months,
            'source_financials' => $budget->source_financials ?? [],
            'client_goals' => $budget->client_goals ?? [],
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_sections' => $budget->business_plan_sections ?? [],
            'business_plan_source_drafts' => $budget->business_plan_source_drafts ?? [],
            'business_plan_prompts' => $budget->business_plan_prompts ?? [],
            'business_plan_readiness_score' => $this->businessPlanReadiness($budget),
            'business_plan_ready' => $this->businessPlanReady($budget),
            'business_plan_submitted_at' => $budget->business_plan_submitted_at?->toIso8601String(),
            'business_plan_approved_at' => $budget->business_plan_approved_at?->toIso8601String(),
            'assumptions' => $budget->assumptions ?? [],
            'implementation_costs' => $budget->implementation_costs ?? [],
            'monthly_fixed_costs' => $budget->monthly_fixed_costs ?? [],
            'future_costs' => $budget->future_costs ?? [],
            'revenue_forecast' => $budget->revenue_forecast ?? [],
            'funding_sources' => $budget->funding_sources ?? [],
            'funding_scenarios' => $budget->funding_scenarios ?? [],
            'computed' => $computed,
            'flags' => $budget->flags ?? [],
            'confidence' => $confidence,
            'readiness_score' => (int) data_get($confidence, 'score', 0),
            'progress_score' => (int) data_get($confidence, 'progress_score', 0),
            'submitted_at' => $budget->submitted_at?->toIso8601String(),
            'approved_at' => $budget->approved_at?->toIso8601String(),
            'used_in_proposal_at' => $budget->used_in_proposal_at?->toIso8601String(),
            'accepted_snapshot_at' => $budget->accepted_snapshot_at?->toIso8601String(),
        ];
    }

    private function recompute(StrategicBudget $budget): StrategicBudget
    {
        if (! $budget->isUnlocked()) {
            return $this->refreshReadiness($budget);
        }

        $computed = $this->calculator->compute(
            launchCosts: (array) ($budget->implementation_costs ?? []),
            monthlyFixedCosts: (array) ($budget->monthly_fixed_costs ?? []),
            revenueForecast: (array) ($budget->revenue_forecast ?? []),
            fundingSources: (array) ($budget->funding_sources ?? []),
            expectedRunwayMonths: $budget->expected_runway_months,
            forecastYears: max(1, (int) ceil(((int) $budget->horizon_months) / 12)),
            assumptions: (array) ($budget->assumptions ?? []),
            futureCosts: (array) ($budget->future_costs ?? []),
            fundingScenarios: (array) ($budget->funding_scenarios ?? []),
            companyTaxRatePercent: $this->economicPercent(EconomicIndicator::COMPANY_TAX_RATE),
            defaultCostInflationPercent: $this->economicPercent(EconomicIndicator::CPI_ANNUAL),
        );
        $confidence = $this->confidence($budget, $computed);
        $flags = $this->flags($budget, $computed, $confidence);

        $budget->forceFill([
            'computed' => $computed,
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    private function refreshReadiness(StrategicBudget $budget): StrategicBudget
    {
        $confidence = $this->confidence($budget, (array) ($budget->computed ?? []));
        $flags = $this->flags($budget, (array) ($budget->computed ?? []), $confidence);

        $budget->forceFill([
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return Collection<int, Document>
     */
    private function financialDocuments(Client $client): Collection
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->latest()
            ->get()
            ->filter(fn (Document $document): bool => $this->isBudgetFinancialDocument($document))
            ->values();
    }

    private function isBudgetFinancialDocument(Document $document): bool
    {
        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return true;
        }

        $filename = str((string) $document->original_filename)->lower()->toString();

        foreach (self::FINANCIAL_KEYWORDS as $keyword) {
            if (str_contains($filename, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, mixed>
     */
    private function sourceFinancialsPayload(Collection $documents): array
    {
        $items = $documents
            ->take(8)
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'detected_as' => $this->detectedFinancialType($document),
            ])
            ->values()
            ->all();

        return [
            'unlocked' => $documents->isNotEmpty(),
            'count' => $documents->count(),
            'items' => $items,
            'required_tags' => ['P&L', 'Management Accounts'],
            'system_review' => $documents->isNotEmpty()
                ? 'Financial upload looks suitable as a starting point.'
                : 'Upload a P&L or management accounts file to unlock the budget.',
        ];
    }

    private function detectedFinancialType(Document $document): string
    {
        $filename = str((string) $document->original_filename)->lower()->toString();

        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return 'Financial statement';
        }

        if (str_contains($filename, 'management')) {
            return 'Management accounts';
        }

        if (str_contains($filename, 'p&l') || str_contains($filename, 'profit')) {
            return 'P&L';
        }

        return 'Financial upload';
    }

    /**
     * @return array<int, array{title:string,measure:string,owner:string,locked:bool}>
     */
    private function clientGoals(Client $client): array
    {
        $state = is_array($client->onboarding_wizard_state) ? $client->onboarding_wizard_state : [];
        $goals = (array) data_get($state, 'steps.goals', []);
        $primary = trim((string) ($goals['primary_goal'] ?? ''));
        $measure = trim((string) ($goals['success_measure'] ?? ''));

        if ($primary === '' && $measure === '') {
            return [];
        }

        return [[
            'title' => $primary !== '' ? $primary : 'Client onboarding goal',
            'measure' => $measure,
            'owner' => 'client',
            'locked' => false,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{key:string,title:string,prompt:string,answer:string}>
     */
    private function normaliseBusinessPlanSections(array $sections, string $pathway): array
    {
        $byKey = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section))
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(function (string $key) use ($byKey, $prompts): array {
                $prompt = (array) ($prompts->get($key) ?? []);
                $section = (array) ($byKey->get($key) ?? []);

                return [
                    'key' => $key,
                    'title' => (string) ($prompt['title'] ?? str($key)->replace('_', ' ')->title()->toString()),
                    'prompt' => (string) ($prompt['prompt'] ?? ''),
                    'answer' => trim((string) ($section['answer'] ?? $section['body'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,prompt:string}>
     */
    private function businessPlanPrompts(string $pathway): array
    {
        $variant = match ($pathway) {
            StrategicBudget::PATHWAY_DUE_DILIGENCE => 'due_diligence',
            StrategicBudget::PATHWAY_NPO => 'npo',
            default => 'advisory',
        };

        $prompts = [
            'advisory' => [
                'goals' => 'Confirm the practical business outcomes this advisory work must support.',
                'current_position' => 'Describe the current operating, financial, and leadership position.',
                'market_customers' => 'Summarise core customers, market position, demand signals, and customer risks.',
                'operations' => 'Explain the operating model, systems, people, capacity, and delivery constraints.',
                'risks' => 'Identify the most important commercial, financial, compliance, people, and execution risks.',
                'swot' => 'Summarise strengths, weaknesses, opportunities, and threats in plain language.',
                'action_priorities' => 'Set the near-term actions that would make the proposal more likely to succeed.',
                'evidence_documents' => 'List the documents, numbers, and evidence that support this plan.',
            ],
            'due_diligence' => [
                'goals' => 'Confirm the acquisition goal, target outcome, and what must be true after settlement.',
                'current_position' => 'Describe the buyer, target, DD status, and acquisition context.',
                'market_customers' => 'Summarise target customers, market position, concentration risk, and demand assumptions.',
                'operations' => 'Explain target operations, handover requirements, systems, people, and integration constraints.',
                'risks' => 'Identify acquisition, valuation, funding, integration, and post-settlement risks.',
                'swot' => 'Summarise the acquisition strengths, weaknesses, opportunities, and threats.',
                'action_priorities' => 'Set the first decision gates, completion actions, and first 100-day priorities.',
                'evidence_documents' => 'List DD evidence, financial uploads, workstream findings, and valuation sources.',
            ],
            'npo' => [
                'goals' => 'Confirm mission, operating, funding, governance, and impact outcomes.',
                'current_position' => 'Describe the current governance, service, funding, operational, and compliance position.',
                'market_customers' => 'Summarise beneficiaries, funders, communities, partners, and demand for services.',
                'operations' => 'Explain programmes, volunteers/staff, delivery capacity, systems, and reporting rhythm.',
                'risks' => 'Identify funding, governance, compliance, service-delivery, and reputation risks.',
                'swot' => 'Summarise mission strengths, capability gaps, opportunities, and threats.',
                'action_priorities' => 'Set practical operating priorities that improve sustainability and impact.',
                'evidence_documents' => 'List funding records, budgets, governance documents, impact evidence, and financial uploads.',
            ],
        ];

        $titles = [
            'goals' => 'Goals',
            'current_position' => 'Current position',
            'market_customers' => 'Market / customers',
            'operations' => 'Operations',
            'risks' => 'Risks',
            'swot' => 'SWOT',
            'action_priorities' => 'Action priorities',
            'evidence_documents' => 'Evidence / documents',
        ];

        return collect(self::PLAN_SECTION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => $titles[$key],
                'prompt' => $prompts[$variant][$key],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,source_label:string,source_url:string,source_help:string,body:string}>
     */
    private function sourceDrafts(Client $client, ?BusinessPlan $plan, string $pathway): array
    {
        $clientGoals = $this->clientGoals($client);
        $goalDraft = collect($clientGoals)
            ->map(fn (array $goal): string => trim($goal['title'].' '.($goal['measure'] ?? '')))
            ->filter()
            ->implode("\n");
        $ddDraft = $this->ddSourceDraft($plan);
        $documentCount = Document::query()
            ->where('client_id', $client->getKey())
            ->count();

        $sourceLabel = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? 'Source draft from Due Diligence'
            : 'Source draft from onboarding and evidence';
        $sourceUrl = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? route('portal.dd-plan.show', absolute: false)
            : route('portal.onboarding.step', ['step' => 'documents'], absolute: false);
        $sourceHelp = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? 'Open the due diligence workspace used to populate this source draft.'
            : 'Open onboarding documents and uploaded evidence used as source material for this draft.';

        $drafts = [
            'goals' => $goalDraft,
            'current_position' => $ddDraft !== ''
                ? $ddDraft
                : trim(($client->trading_name ?: $client->legal_name).' is in the '.$this->engagementLabel($client).' pathway.'),
            'market_customers' => '',
            'operations' => '',
            'risks' => '',
            'swot' => '',
            'action_priorities' => '',
            'evidence_documents' => $documentCount > 0
                ? "{$documentCount} document(s) are available as plan evidence. Confirm which documents support each section."
                : 'No supporting evidence has been attached to this plan yet.',
        ];
        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => (string) data_get($prompts->get($key), 'title', str($key)->replace('_', ' ')->title()->toString()),
                'source_label' => $sourceLabel,
                'source_url' => $sourceUrl,
                'source_help' => $sourceHelp,
                'body' => trim((string) ($drafts[$key] ?? '')),
            ])
            ->values()
            ->all();
    }

    private function ddSourceDraft(?BusinessPlan $plan): string
    {
        if (! $plan instanceof BusinessPlan) {
            return '';
        }

        return $plan->sections()
            ->latest('updated_at')
            ->take(5)
            ->get(['title', 'body'])
            ->map(fn ($section): string => trim($section->title.': '.$section->body))
            ->filter()
            ->implode("\n\n");
    }

    private function engagementLabel(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType?->label() ?? str((string) $client->engagement_type)->replace('_', ' ')->title()->toString();
    }

    private function businessPlanReadiness(StrategicBudget $budget): int
    {
        $sections = (array) ($budget->business_plan_sections ?? []);
        if ($sections === []) {
            return 0;
        }

        $completed = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section) && trim((string) ($section['answer'] ?? '')) !== '')
            ->count();

        return (int) round(($completed / count(self::PLAN_SECTION_KEYS)) * 100);
    }

    private function businessPlanReady(StrategicBudget $budget): bool
    {
        return $this->businessPlanReadiness($budget) >= 100;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<string, mixed>
     */
    private function confidence(StrategicBudget $budget, array $computed): array
    {
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $hasFinancials = (bool) ($sourceFinancials['unlocked'] ?? false);
        $inputCount = (int) ($computed['input_count'] ?? 0);
        $missingAssumptions = (array) ($computed['missing_assumptions'] ?? []);
        $rowConfidence = $this->rowConfidence(
            (array) ($budget->implementation_costs ?? []),
            (array) ($budget->monthly_fixed_costs ?? []),
            (array) ($budget->future_costs ?? []),
            (array) ($budget->revenue_forecast ?? []),
            (array) ($budget->funding_sources ?? []),
            (array) ($budget->funding_scenarios ?? []),
        );

        $sourceScore = $hasFinancials ? 30 : 0;
        $inputScore = min(30, $inputCount * 4);
        $assumptionScore = max(0, 20 - (count($missingAssumptions) * 4));
        $rowScore = (int) round((float) ($rowConfidence['confidence_ratio'] ?? 0) * 20);
        $score = max(0, min(100, $sourceScore + $inputScore + $assumptionScore + $rowScore));

        return [
            'score' => $score,
            'progress_score' => $this->progressScore($budget, $computed, $hasFinancials),
            'source_score' => $sourceScore,
            'input_score' => $inputScore,
            'assumption_score' => $assumptionScore,
            'row_confidence_score' => $rowScore,
            'row_confidence' => $rowConfidence,
            'overall' => match (true) {
                $score >= 80 => 'strong',
                $score >= 55 => 'developing',
                $score > 0 => 'preliminary',
                default => 'locked',
            },
            'message' => $this->confidenceMessage($score, $hasFinancials),
        ];
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function progressScore(StrategicBudget $budget, array $computed, bool $hasFinancials): int
    {
        $steps = [
            $this->businessPlanReady($budget),
            $hasFinancials,
            ((array) ($budget->implementation_costs ?? [])) !== [],
            ((array) ($budget->monthly_fixed_costs ?? [])) !== [],
            ((array) ($budget->revenue_forecast ?? [])) !== [],
            ((array) ($budget->funding_sources ?? [])) !== [],
            $budget->expected_runway_months !== null,
            ((array) ($computed['missing_assumptions'] ?? [])) === [],
            in_array($budget->status, [
                StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true),
            $budget->isApprovedForProposal(),
        ];

        return (int) round((count(array_filter($steps)) / count($steps)) * 100);
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array{known:int,estimate:int,guess:int,total:int,guess_ratio:float,confidence_ratio:float}
     */
    private function rowConfidence(array ...$groups): array
    {
        $summary = ['known' => 0, 'estimate' => 0, 'guess' => 0, 'total' => 0, 'guess_ratio' => 0.0, 'confidence_ratio' => 0.0];

        foreach ($groups as $group) {
            foreach ($group as $row) {
                $confidence = in_array($row['confidence'] ?? '', ['known', 'estimate', 'guess'], true)
                    ? (string) $row['confidence']
                    : 'estimate';
                $summary[$confidence]++;
                $summary['total']++;
            }
        }

        if ($summary['total'] > 0) {
            $summary['guess_ratio'] = round($summary['guess'] / $summary['total'], 4);
            $weighted = ($summary['known'] * 1) + ($summary['estimate'] * 0.65) + ($summary['guess'] * 0.2);
            $summary['confidence_ratio'] = round($weighted / $summary['total'], 4);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array<string, string>>
     */
    private function flags(StrategicBudget $budget, array $computed, array $confidence): array
    {
        $flags = [];
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $planLabel = $budget->pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan'
            : 'Business Plan';

        if (! (bool) ($sourceFinancials['unlocked'] ?? false)) {
            $flags[] = $this->flag('financial_upload_required', 'Upload financials', 'Upload a P&L or management accounts file before the budget can be edited.', 'high');
        } elseif ((int) ($sourceFinancials['count'] ?? 0) < 2) {
            $flags[] = $this->flag('partial_financials', 'Preliminary financial base', 'Only one qualifying financial upload is present. The budget can start, but more source files will improve the business plan and proposal.', 'medium');
        }

        if (! $this->businessPlanReady($budget)) {
            $flags[] = $this->flag('business_plan_incomplete', "{$planLabel} needs completion", "Complete every {$planLabel} section before submitting the combined plan and budget for advisor approval.", 'medium');
        }

        if (((array) ($budget->implementation_costs ?? [])) === []) {
            $flags[] = $this->flag('implementation_costs_missing', 'Implementation costs needed', 'Add the one-off setup, transition, advisory, or project costs that the plan needs to fund.', 'medium');
        }

        if (((array) ($budget->revenue_forecast ?? [])) === []) {
            $flags[] = $this->flag('revenue_forecast_missing', 'Revenue forecast needed', 'Add the expected revenue lines so affordability and proposal timing can be assessed.', 'medium');
        }

        if ((array) ($computed['missing_assumptions'] ?? []) !== []) {
            $flags[] = $this->flag('missing_assumptions', 'Financial assumptions need detail', 'Growth, margin, inflation, or profit-target assumptions are missing. This lowers the confidence score.', 'medium');
        }

        if (($confidence['row_confidence']['guess_ratio'] ?? 0) >= 0.5) {
            $flags[] = $this->flag('too_many_guesses', 'Too many guessed rows', 'Replace the highest-value guesses with uploaded evidence, advisor-reviewed estimates, or client-confirmed figures.', 'medium');
        }

        if (($computed['input_count'] ?? 0) > 0 && ! (bool) ($computed['break_even_reached'] ?? false)) {
            $flags[] = $this->flag('no_break_even', 'Break-even not visible', 'The current budget does not yet show a break-even year. This should be addressed before relying on the proposal.', 'medium');
        }

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $flags[] = $this->flag('tax_not_configured', 'Company tax rate not configured', 'After-tax profit uses a warning state until Admin reference data has a current company tax rate.', 'medium');
        }

        return $flags;
    }

    /**
     * @return array{key:string,title:string,message:string,severity:string}
     */
    private function flag(string $key, string $title, string $message, string $severity): array
    {
        return compact('key', 'title', 'message', 'severity');
    }

    private function pathway(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE => StrategicBudget::PATHWAY_DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => StrategicBudget::PATHWAY_POST_ACQUISITION,
            EngagementType::NPO => StrategicBudget::PATHWAY_NPO,
            default => StrategicBudget::PATHWAY_ADVISORY,
        };
    }

    private function label(string $pathway): string
    {
        return $pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan & Budget'
            : 'Business Plan & Budget';
    }

    private function defaultHorizonMonths(Client $client): int
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => 24,
            default => 12,
        };
    }

    private function horizonMonths(mixed $value): int
    {
        $months = is_numeric($value) ? (int) $value : 12;

        return in_array($months, [12, 24, 36], true) ? $months : 12;
    }

    private function expectedRunway(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? min(60, max(0, (int) $value)) : null;
    }

    private function economicPercent(string $indicator): ?float
    {
        $value = EconomicIndicator::query()
            ->where('indicator', $indicator)
            ->latest('period_date')
            ->latest('fetched_at')
            ->value('value');

        return is_numeric($value) ? (float) $value : null;
    }

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function confidenceMessage(int $score, bool $hasFinancials): string
    {
        if (! $hasFinancials) {
            return 'Upload a P&L or management accounts file to unlock this budget.';
        }

        return match (true) {
            $score >= 80 => 'Budget confidence is strong enough for advisor proposal readiness review.',
            $score >= 55 => 'Budget confidence is developing; review flagged assumptions before proposal generation.',
            default => 'Budget confidence is preliminary and will adversely affect the business plan and proposal unless improved.',
        };
    }
}
