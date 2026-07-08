<?php

declare(strict_types=1);

namespace App\Services\StrategicPlans;

use App\Enums\EngagementType;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\StrategicBudget;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Calendar\PublicHolidayCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StrategicPlanService
{
    private const SECTION_KEYS = [
        'outcomes',
        'priorities',
        'milestones',
        'budget',
        'governance',
    ];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly PublicHolidayCalendar $publicHolidays,
    ) {}

    public function generateForProposal(Proposal $proposal, User $actor): StrategicPlan
    {
        if ($proposal->status !== ProposalStatus::Signed) {
            throw new InvalidArgumentException('A strategic plan can only be generated after the proposal is accepted.');
        }

        $proposal->loadMissing(['client', 'feeCalculation']);
        $client = $proposal->client;

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Proposal must belong to a client.');
        }

        $budget = $this->budgetForProposal($proposal);

        return DB::transaction(function () use ($proposal, $client, $budget, $actor): StrategicPlan {
            $plan = StrategicPlan::query()->firstOrNew([
                'proposal_id' => $proposal->getKey(),
            ]);
            $wasRecentlyCreated = ! $plan->exists;

            if ($plan->exists && $plan->status === StrategicPlan::STATUS_DEPLOYED) {
                return $plan->load('milestones');
            }

            $plan->forceFill([
                'client_id' => $client->getKey(),
                'strategic_budget_id' => $budget?->getKey(),
                'title' => 'Strategic Plan - '.($client->trading_name ?: $client->legal_name),
                'status' => StrategicPlan::STATUS_DRAFT,
                'summary' => $this->summary($client, $proposal, $budget),
                'sections' => $this->sections($client, $proposal, $budget),
                'generated_at' => now(),
                'generated_by_user_id' => $actor->getKey(),
            ])->save();

            if ($wasRecentlyCreated || $plan->milestones()->count() === 0) {
                $this->seedMilestones($plan->refresh(), $client, $proposal);
            }

            $this->audit->record('strategic_plan.generated', subject: $plan, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'proposal_id' => $proposal->getKey(),
            ]);

            return $plan->refresh()->load('milestones');
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(StrategicPlan $plan, array $input, User $actor): StrategicPlan
    {
        if ($plan->status === StrategicPlan::STATUS_DEPLOYED) {
            throw new InvalidArgumentException('Deployed strategic plans cannot have their structure changed after deployment.');
        }

        return DB::transaction(function () use ($plan, $input, $actor): StrategicPlan {
            $plan->forceFill([
                'summary' => trim((string) ($input['summary'] ?? $plan->summary ?? '')),
                'sections' => $this->normaliseSections((array) ($input['sections'] ?? $plan->sections ?? [])),
            ])->save();

            $this->syncMilestones($plan->refresh(), (array) ($input['milestones'] ?? []));

            $this->audit->record('strategic_plan.updated', subject: $plan, actor: $actor, after: [
                'client_id' => $plan->client_id,
                'milestone_count' => $plan->milestones()->count(),
            ]);

            return $plan->refresh()->load('milestones');
        });
    }

    public function deploy(StrategicPlan $plan, User $actor): StrategicPlan
    {
        return DB::transaction(function () use ($plan, $actor): StrategicPlan {
            $deploymentDate = now();
            $plan->loadMissing('client');
            $client = $plan->client;
            $regions = $client instanceof Client ? $this->publicHolidays->regionsForClient($client) : [];

            $plan->forceFill([
                'status' => StrategicPlan::STATUS_DEPLOYED,
                'deployed_at' => $deploymentDate,
                'deployed_by_user_id' => $actor->getKey(),
            ])->save();

            $plan->milestones()->get()->each(function (StrategicPlanMilestone $milestone) use ($deploymentDate, $regions): void {
                $dueDate = $this->publicHolidays->nextAvailableDate(
                    $deploymentDate->copy()->addDays((int) $milestone->due_offset_days),
                    $regions,
                );

                $milestone->forceFill([
                    'due_date' => $dueDate->toDateString(),
                    'status' => $milestone->status ?: StrategicPlanMilestone::STATUS_PENDING,
                ])->save();
            });

            $this->audit->record('strategic_plan.deployed', subject: $plan, actor: $actor, after: [
                'client_id' => $plan->client_id,
                'deployed_at' => $deploymentDate->toIso8601String(),
            ]);

            return $plan->refresh()->load('milestones');
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateClientMilestone(StrategicPlanMilestone $milestone, array $input, User $actor): StrategicPlanMilestone
    {
        $milestone->loadMissing('strategicPlan');
        $plan = $milestone->strategicPlan;

        if (! $plan instanceof StrategicPlan || $plan->status !== StrategicPlan::STATUS_DEPLOYED) {
            throw new InvalidArgumentException('Only deployed strategic plan milestones can be updated by the client.');
        }

        $status = (string) ($input['status'] ?? $milestone->status);
        $progress = min(100, max(0, (int) ($input['progress_percent'] ?? $milestone->progress_percent)));

        $milestone->forceFill([
            'status' => $status,
            'progress_percent' => $status === StrategicPlanMilestone::STATUS_COMPLETED ? 100 : $progress,
            'evidence_notes' => trim((string) ($input['evidence_notes'] ?? $milestone->evidence_notes ?? '')),
            'completed_at' => $status === StrategicPlanMilestone::STATUS_COMPLETED
                ? ($milestone->completed_at ?? now())
                : null,
        ])->save();

        $this->audit->record('strategic_plan_milestone.client_updated', subject: $milestone, actor: $actor, after: [
            'status' => $milestone->status,
            'progress_percent' => $milestone->progress_percent,
        ]);

        return $milestone->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function advisorPayload(Client $client): ?array
    {
        $plan = $this->latestForClient($client);

        return $plan instanceof StrategicPlan ? $this->payload($plan, true) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function portalPayload(Client $client): ?array
    {
        $plan = $this->latestForClient($client, deployedOnly: true);

        return $plan instanceof StrategicPlan ? $this->payload($plan, false) : null;
    }

    private function latestForClient(Client $client, bool $deployedOnly = false): ?StrategicPlan
    {
        $query = StrategicPlan::query()
            ->with('milestones')
            ->where('client_id', $client->getKey())
            ->latest('deployed_at')
            ->latest('generated_at')
            ->latest();

        if ($deployedOnly) {
            $query->where('status', StrategicPlan::STATUS_DEPLOYED);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StrategicPlan $plan, bool $advisor): array
    {
        $milestones = $plan->milestones->sortBy('due_offset_days')->values();
        $completed = $milestones->where('status', StrategicPlanMilestone::STATUS_COMPLETED)->count();
        $total = $milestones->count();
        $averageProgress = $total > 0
            ? (int) round($milestones->avg('progress_percent'))
            : 0;

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'status_label' => str($plan->status)->replace('_', ' ')->title()->toString(),
            'summary' => $plan->summary,
            'sections' => $plan->sections ?? [],
            'generated_at' => $plan->generated_at?->toIso8601String(),
            'deployed_at' => $plan->deployed_at?->toIso8601String(),
            'progress_percent' => $averageProgress,
            'completed_milestones' => $completed,
            'total_milestones' => $total,
            'milestones' => $milestones
                ->map(fn (StrategicPlanMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'owner' => $milestone->owner,
                    'owner_label' => str($milestone->owner)->replace('_', ' ')->title()->toString(),
                    'due_offset_days' => $milestone->due_offset_days,
                    'due_date' => $milestone->due_date?->toDateString(),
                    'status' => $milestone->status,
                    'status_label' => str($milestone->status)->replace('_', ' ')->title()->toString(),
                    'progress_percent' => $milestone->progress_percent,
                    'evidence_notes' => $milestone->evidence_notes,
                    'advisor_notes' => $milestone->advisor_notes,
                    ...(! $advisor ? [
                        'update_url' => route('portal.strategic-plan.milestones.update', $milestone, absolute: false),
                    ] : []),
                ])
                ->values()
                ->all(),
            ...($advisor ? [
                'pdf_url' => route('advisor.strategic-plans.pdf', $plan, absolute: false),
                'update_url' => route('advisor.strategic-plans.update', $plan, absolute: false),
                'deploy_url' => route('advisor.strategic-plans.deploy', $plan, absolute: false),
            ] : []),
        ];
    }

    private function budgetForProposal(Proposal $proposal): ?StrategicBudget
    {
        $budget = StrategicBudget::query()
            ->where('proposal_id', $proposal->getKey())
            ->latest()
            ->first();

        if ($budget instanceof StrategicBudget) {
            return $budget;
        }

        return StrategicBudget::query()
            ->where('client_id', $proposal->client_id)
            ->whereIn('status', [
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ])
            ->latest('used_in_proposal_at')
            ->latest('approved_at')
            ->first();
    }

    private function summary(Client $client, Proposal $proposal, ?StrategicBudget $budget): string
    {
        $fee = $proposal->feeCalculation?->suggested_mid;
        $focusAreaCount = count($this->proposalFocusAreas($proposal));
        $feeLine = is_numeric($fee)
            ? 'Accepted proposal value: NZD '.number_format((float) $fee, 0).' ex GST.'
            : 'Accepted proposal value is not available.';
        $budgetLine = $budget instanceof StrategicBudget
            ? 'Plan and budget confidence: '.(int) data_get($budget->confidence, 'score', 0).'/100.'
            : 'No approved Business/Operating Plan & Budget snapshot is linked.';
        $focusAreaLine = $focusAreaCount > 0
            ? "Proposal fix priorities carried into this plan: {$focusAreaCount}."
            : 'No proposal fix priorities are attached to this plan.';

        return trim(sprintf(
            "%s strategic plan generated after proposal acceptance.\n%s\n%s\n%s",
            $client->trading_name ?: $client->legal_name,
            $feeLine,
            $budgetLine,
            $focusAreaLine,
        ));
    }

    /**
     * @return array<int, array{key:string,title:string,body:string}>
     */
    private function sections(Client $client, Proposal $proposal, ?StrategicBudget $budget): array
    {
        $planSections = collect((array) ($budget?->business_plan_sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->mapWithKeys(fn (array $section): array => [(string) ($section['key'] ?? '') => (string) ($section['answer'] ?? '')]);
        $proposalPriorities = $this->proposalFocusAreaText($proposal);
        $goals = collect((array) ($budget?->client_goals ?? []))
            ->merge((array) ($budget?->advisor_goals ?? []))
            ->map(fn (array $goal): string => trim((string) ($goal['title'] ?? '').' '.(string) ($goal['measure'] ?? '')))
            ->filter()
            ->implode("\n");
        $engagement = $this->engagementLabel($client);

        return $this->normaliseSections([
            [
                'key' => 'outcomes',
                'title' => 'Target outcomes',
                'body' => $goals !== '' ? $goals : "Confirm the {$engagement} outcomes from the accepted proposal meeting.",
            ],
            [
                'key' => 'priorities',
                'title' => 'Action priorities',
                'body' => $this->actionPrioritiesBody(
                    (string) ($planSections->get('action_priorities') ?: ''),
                    $proposalPriorities,
                ),
            ],
            [
                'key' => 'milestones',
                'title' => 'Milestone approach',
                'body' => 'Milestone due dates are set from the agreed start date and owned by the client, advisor, or both.',
            ],
            [
                'key' => 'budget',
                'title' => 'Budget and affordability',
                'body' => (string) (
                    $planSections->get('budget_and_affordability')
                    ?: $planSections->get('budget')
                    ?: $planSections->get('payment_terms')
                    ?: 'Use the approved Business/Operating Plan & Budget and accepted proposal payment terms.'
                ),
            ],
            [
                'key' => 'governance',
                'title' => 'Review rhythm',
                'body' => 'Advisor reviews progress with the client, updates milestone status, and records evidence before each proposal-success review.',
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{key:string,title:string,body:string}>
     */
    private function normaliseSections(array $sections): array
    {
        $byKey = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section))
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $titles = [
            'outcomes' => 'Target outcomes',
            'priorities' => 'Action priorities',
            'milestones' => 'Milestone approach',
            'budget' => 'Budget and affordability',
            'governance' => 'Review rhythm',
        ];

        return collect(self::SECTION_KEYS)
            ->map(function (string $key) use ($byKey, $titles): array {
                $section = (array) ($byKey->get($key) ?? []);

                return [
                    'key' => $key,
                    'title' => (string) ($section['title'] ?? $titles[$key]),
                    'body' => trim((string) ($section['body'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    private function seedMilestones(StrategicPlan $plan, Client $client, Proposal $proposal): void
    {
        foreach ([...$this->defaultMilestones($client), ...$this->proposalFocusAreaMilestones($proposal)] as $milestone) {
            $plan->milestones()->create([
                'client_id' => $client->getKey(),
                ...$milestone,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $milestones
     */
    private function syncMilestones(StrategicPlan $plan, array $milestones): void
    {
        $seen = [];

        foreach ($milestones as $input) {
            if (! is_array($input)) {
                continue;
            }

            $title = trim((string) ($input['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $id = (string) ($input['id'] ?? '');
            $milestone = $id !== ''
                ? $plan->milestones()->whereKey($id)->first()
                : null;
            $milestone ??= new StrategicPlanMilestone([
                'strategic_plan_id' => $plan->getKey(),
                'client_id' => $plan->client_id,
            ]);

            $owner = (string) ($input['owner'] ?? StrategicPlanMilestone::OWNER_JOINT);
            $status = (string) ($input['status'] ?? StrategicPlanMilestone::STATUS_PENDING);

            $milestone->forceFill([
                'title' => $title,
                'description' => trim((string) ($input['description'] ?? '')),
                'owner' => in_array($owner, [
                    StrategicPlanMilestone::OWNER_CLIENT,
                    StrategicPlanMilestone::OWNER_ADVISOR,
                    StrategicPlanMilestone::OWNER_JOINT,
                ], true) ? $owner : StrategicPlanMilestone::OWNER_JOINT,
                'due_offset_days' => min(365, max(1, (int) ($input['due_offset_days'] ?? 30))),
                'status' => in_array($status, [
                    StrategicPlanMilestone::STATUS_PENDING,
                    StrategicPlanMilestone::STATUS_IN_PROGRESS,
                    StrategicPlanMilestone::STATUS_COMPLETED,
                    StrategicPlanMilestone::STATUS_BLOCKED,
                ], true) ? $status : StrategicPlanMilestone::STATUS_PENDING,
                'progress_percent' => min(100, max(0, (int) ($input['progress_percent'] ?? 0))),
                'advisor_notes' => trim((string) ($input['advisor_notes'] ?? '')),
            ])->save();

            $seen[] = $milestone->getKey();
        }

        if ($seen !== []) {
            $plan->milestones()->whereNotIn('id', $seen)->delete();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultMilestones(Client $client): array
    {
        $prefix = $client->engagement_type instanceof EngagementType && $client->engagement_type === EngagementType::DUE_DILIGENCE
            ? 'acquisition'
            : 'advisory';

        return [
            [
                'title' => 'Strategic plan review meeting',
                'description' => 'Advisor explains the accepted proposal, plan priorities, roles, budget implications, and first decisions.',
                'owner' => StrategicPlanMilestone::OWNER_JOINT,
                'due_offset_days' => 7,
            ],
            [
                'title' => 'Confirm '.$prefix.' priorities',
                'description' => 'Advisor confirms the final workstreams, priority order, and success measures with the client.',
                'owner' => StrategicPlanMilestone::OWNER_ADVISOR,
                'due_offset_days' => 14,
            ],
            [
                'title' => 'Complete client evidence actions',
                'description' => 'Client uploads or confirms evidence needed for the first implementation phase.',
                'owner' => StrategicPlanMilestone::OWNER_CLIENT,
                'due_offset_days' => 21,
            ],
            [
                'title' => 'Execute first implementation sprint',
                'description' => 'Joint delivery of the first agreed actions and status evidence.',
                'owner' => StrategicPlanMilestone::OWNER_JOINT,
                'due_offset_days' => 45,
            ],
            [
                'title' => 'Review progress and reset next milestones',
                'description' => 'Advisor and client review progress, evidence, blockers, and next-step adjustments.',
                'owner' => StrategicPlanMilestone::OWNER_JOINT,
                'due_offset_days' => 90,
            ],
        ];
    }

    private function actionPrioritiesBody(string $budgetPriorities, string $proposalPriorities): string
    {
        $parts = array_values(array_filter([
            trim($proposalPriorities),
            trim($budgetPriorities),
        ]));

        if ($parts === []) {
            return 'Confirm the first priorities with the client before the plan starts.';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array<int, array{title:string,body:string,module:string|null}>
     */
    private function proposalFocusAreas(Proposal $proposal): array
    {
        return collect((array) data_get($proposal->scope, 'focus_areas', []))
            ->filter(fn (mixed $area): bool => is_array($area))
            ->map(function (array $area): array {
                return [
                    'title' => trim((string) ($area['title'] ?? 'Advisory focus area')),
                    'body' => trim((string) ($area['body'] ?? '')),
                    'module' => is_string($area['module'] ?? null) ? $area['module'] : null,
                ];
            })
            ->filter(fn (array $area): bool => $area['title'] !== '' || $area['body'] !== '')
            ->take(6)
            ->values()
            ->all();
    }

    private function proposalFocusAreaText(Proposal $proposal): string
    {
        $focusAreas = $this->proposalFocusAreas($proposal);

        if ($focusAreas === []) {
            return '';
        }

        $lines = array_map(
            static fn (array $area): string => '- '.$area['title'].($area['body'] !== '' ? ': '.$area['body'] : ''),
            $focusAreas,
        );

        return "Proposal fix priorities:\n".implode("\n", $lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalFocusAreaMilestones(Proposal $proposal): array
    {
        return collect($this->proposalFocusAreas($proposal))
            ->take(4)
            ->values()
            ->map(fn (array $area, int $index): array => [
                'title' => Str::limit('Deliver fix: '.$area['title'], 160, ''),
                'description' => Str::limit($area['body'] !== ''
                    ? $area['body']
                    : 'Deliver the proposal focus area agreed with the client.', 1000, ''),
                'owner' => StrategicPlanMilestone::OWNER_JOINT,
                'due_offset_days' => 21 + ($index * 14),
            ])
            ->all();
    }

    private function engagementLabel(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType?->label() ?? str((string) $client->engagement_type)->replace('_', ' ')->title()->toString();
    }
}
