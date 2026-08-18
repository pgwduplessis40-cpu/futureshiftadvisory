<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\FoundingAdvisoryEngagement;
use App\Models\FoundingRoadmapVersion;
use App\Models\PlanAssessment;
use App\Models\Proposal;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\StrategicPlans\StrategicPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FoundingAdvisoryService
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly BusinessPlanSnapshot $snapshots,
        private readonly StrategicBudgetService $strategicBudgets,
        private readonly StrategicPlanService $strategicPlans,
    ) {}

    public function openForConversion(
        Client $client,
        EntrepreneurProfile $profile,
        BusinessPlan $plan,
        User $advisor,
    ): FoundingAdvisoryEngagement {
        $plan->loadMissing('assessments');
        $assessment = $plan->assessments
            ->filter(fn (PlanAssessment $candidate): bool => $candidate->finalised_at !== null)
            ->sortByDesc('round')
            ->first();
        $signal = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('business_plan_id', $plan->getKey())
            ->latest('surfaced_at')
            ->first();

        return FoundingAdvisoryEngagement::query()->firstOrCreate(
            ['client_id' => $client->getKey()],
            [
                'entrepreneur_profile_id' => $profile->getKey(),
                'business_plan_id' => $plan->getKey(),
                'plan_assessment_id' => $assessment?->getKey(),
                'advisory_readiness_signal_id' => $signal?->getKey(),
                'baseline' => $this->baseline($profile, $plan, $assessment, $signal),
                'status' => FoundingAdvisoryEngagement::STATUS_ADVISORY_READY,
                'prepared_by_user_id' => $advisor->getKey(),
            ],
        );
    }

    public function attachProposal(Client $client, Proposal $proposal, User $advisor): ?FoundingAdvisoryEngagement
    {
        $engagement = FoundingAdvisoryEngagement::query()
            ->where('client_id', $client->getKey())
            ->first();

        if (! $engagement instanceof FoundingAdvisoryEngagement) {
            return null;
        }

        $engagement->forceFill([
            'proposal_id' => $proposal->getKey(),
            'status' => FoundingAdvisoryEngagement::STATUS_PROPOSAL_DRAFT,
            'prepared_by_user_id' => $advisor->getKey(),
        ])->save();

        $this->audit->record('founding_advisory.proposal_prepared', subject: $engagement, actor: $advisor, after: [
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
        ]);

        return $engagement->refresh();
    }

    public function forClient(Client $client): ?FoundingAdvisoryEngagement
    {
        return FoundingAdvisoryEngagement::query()
            ->with('businessPlan')
            ->where('client_id', $client->getKey())
            ->first();
    }

    public function markProposalReleased(Proposal $proposal, User $advisor): void
    {
        $engagement = FoundingAdvisoryEngagement::query()
            ->where('proposal_id', $proposal->getKey())
            ->first();

        if (! $engagement instanceof FoundingAdvisoryEngagement) {
            return;
        }

        $engagement->forceFill([
            'status' => FoundingAdvisoryEngagement::STATUS_PROPOSAL_SENT,
        ])->save();

        $this->audit->record('founding_advisory.proposal_released', subject: $engagement, actor: $advisor, after: [
            'proposal_id' => $proposal->getKey(),
        ]);
    }

    public function activateSignedProposal(Proposal $proposal, User $actor): ?FoundingRoadmapVersion
    {
        $engagement = FoundingAdvisoryEngagement::query()
            ->where('proposal_id', $proposal->getKey())
            ->first();

        if (! $engagement instanceof FoundingAdvisoryEngagement) {
            return null;
        }

        return DB::transaction(function () use ($engagement, $proposal, $actor): FoundingRoadmapVersion {
            $engagement = FoundingAdvisoryEngagement::query()
                ->whereKey($engagement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $existing = $engagement->roadmapVersions()
                ->latest('version')
                ->first();

            if ($existing instanceof FoundingRoadmapVersion) {
                return $existing;
            }

            $engagement->forceFill([
                'status' => FoundingAdvisoryEngagement::STATUS_ACCEPTED,
                'accepted_at' => $engagement->accepted_at ?? now(),
                'activated_by_user_id' => $actor->getKey(),
            ])->save();

            return $this->createDraftVersion(
                engagement: $engagement,
                proposal: $proposal,
                actor: $this->advisorFor($engagement, $actor),
                replanInput: [],
                changeSummary: [
                    'reason' => 'Initial plan generated from Founding Baseline v1 after proposal acceptance.',
                    'changes' => ['Initial 270-day rolling roadmap created.'],
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function draftReplan(FoundingAdvisoryEngagement $engagement, array $input, User $advisor): FoundingRoadmapVersion
    {
        return DB::transaction(function () use ($engagement, $input, $advisor): FoundingRoadmapVersion {
            $engagement = FoundingAdvisoryEngagement::query()
                ->whereKey($engagement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($engagement->status, [
                FoundingAdvisoryEngagement::STATUS_ACTIVE,
                FoundingAdvisoryEngagement::STATUS_REPLAN_DUE,
                FoundingAdvisoryEngagement::STATUS_MOBILISING,
            ], true)) {
                throw new InvalidArgumentException('A rolling roadmap can be revised once the Founding Advisory engagement is active.');
            }

            $engagement->roadmapVersions()
                ->where('status', FoundingRoadmapVersion::STATUS_DRAFT)
                ->update(['status' => FoundingRoadmapVersion::STATUS_SUPERSEDED]);

            return $this->createDraftVersion(
                engagement: $engagement,
                proposal: null,
                actor: $advisor,
                replanInput: $this->normaliseReplanInput($input),
                changeSummary: [
                    'reason' => trim((string) ($input['reason'] ?? '90-day planning review')),
                    'changes' => $this->changeLines($input),
                ],
            );
        });
    }

    public function publish(FoundingRoadmapVersion $roadmap, User $advisor): FoundingRoadmapVersion
    {
        return DB::transaction(function () use ($roadmap, $advisor): FoundingRoadmapVersion {
            $roadmap = FoundingRoadmapVersion::query()
                ->with(['engagement.client', 'strategicPlan'])
                ->whereKey($roadmap->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($roadmap->status !== FoundingRoadmapVersion::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only a draft roadmap can be published.');
            }

            $engagement = $roadmap->engagement;
            $plan = $roadmap->strategicPlan;
            if (! $engagement instanceof FoundingAdvisoryEngagement || ! $plan instanceof StrategicPlan) {
                throw new InvalidArgumentException('The roadmap is missing its founding engagement or execution plan.');
            }

            $engagement->roadmapVersions()
                ->where('status', FoundingRoadmapVersion::STATUS_PUBLISHED)
                ->update(['status' => FoundingRoadmapVersion::STATUS_SUPERSEDED]);

            $this->seedCommittedMilestones($plan, $roadmap);
            $this->strategicPlans->deploy($plan, $advisor);

            $publishedAt = now();
            $roadmap->forceFill([
                'status' => FoundingRoadmapVersion::STATUS_PUBLISHED,
                'published_at' => $publishedAt,
                'published_by_user_id' => $advisor->getKey(),
            ])->save();

            $engagement->forceFill([
                'status' => FoundingAdvisoryEngagement::STATUS_ACTIVE,
                'started_at' => $engagement->started_at ?? $publishedAt,
                'replan_due_at' => $publishedAt->copy()->addDays(75),
                'transition_review_at' => $engagement->started_at?->copy()->addDays(270) ?? $publishedAt->copy()->addDays(270),
            ])->save();

            $this->audit->record('founding_advisory.roadmap_published', subject: $roadmap, actor: $advisor, after: [
                'engagement_id' => $engagement->getKey(),
                'roadmap_version' => $roadmap->version,
                'strategic_plan_id' => $plan->getKey(),
                'replan_due_at' => $engagement->replan_due_at?->toIso8601String(),
            ]);

            return $roadmap->refresh()->load('strategicPlan.milestones');
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function advisorPayload(Client $client): ?array
    {
        $engagement = FoundingAdvisoryEngagement::query()
            ->with(['roadmapVersions.strategicPlan.milestones'])
            ->where('client_id', $client->getKey())
            ->first();

        return $engagement instanceof FoundingAdvisoryEngagement
            ? $this->payload($engagement, true)
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function founderPayload(EntrepreneurProfile $profile): ?array
    {
        $engagement = FoundingAdvisoryEngagement::query()
            ->with(['roadmapVersions.strategicPlan.milestones'])
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->first();

        return $engagement instanceof FoundingAdvisoryEngagement
            ? $this->payload($engagement, false)
            : null;
    }

    /**
     * @param  array<string, mixed>  $replanInput
     * @param  array<string, mixed>  $changeSummary
     */
    private function createDraftVersion(
        FoundingAdvisoryEngagement $engagement,
        ?Proposal $proposal,
        User $actor,
        array $replanInput,
        array $changeSummary,
    ): FoundingRoadmapVersion {
        $engagement->loadMissing('client', 'businessPlan');
        $client = $engagement->client;
        $businessPlan = $engagement->businessPlan;
        if (! $client instanceof Client || ! $businessPlan instanceof BusinessPlan) {
            throw new InvalidArgumentException('The founding engagement is missing its client or business-plan baseline.');
        }

        $start = CarbonImmutable::now()->startOfDay();
        $agenda = $this->agenda($engagement, $start, $replanInput);
        $nextVersion = ((int) $engagement->roadmapVersions()->max('version')) + 1;
        $budget = $this->strategicBudgets->ensureForClient($client, $businessPlan);
        $plan = StrategicPlan::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal?->getKey(),
            'strategic_budget_id' => $budget->getKey(),
            'title' => 'Founding Roadmap v'.$nextVersion.' - '.($client->trading_name ?: $client->legal_name),
            'status' => StrategicPlan::STATUS_DRAFT,
            'summary' => $this->roadmapSummary($engagement, $agenda),
            'sections' => $this->roadmapSections($agenda),
            'generated_at' => now(),
            'generated_by_user_id' => $actor->getKey(),
        ]);

        $roadmap = FoundingRoadmapVersion::query()->create([
            'founding_advisory_engagement_id' => $engagement->getKey(),
            'client_id' => $client->getKey(),
            'strategic_plan_id' => $plan->getKey(),
            'version' => $nextVersion,
            'status' => FoundingRoadmapVersion::STATUS_DRAFT,
            'planning_start_date' => $start->toDateString(),
            'planning_through_date' => $start->addDays(270)->toDateString(),
            'agenda' => $agenda,
            'replan_input' => $replanInput === [] ? null : $replanInput,
            'change_summary' => $changeSummary,
            'generated_at' => now(),
            'generated_by_user_id' => $actor->getKey(),
        ]);

        $engagement->forceFill([
            'status' => FoundingAdvisoryEngagement::STATUS_MOBILISING,
        ])->save();

        $this->audit->record('founding_advisory.roadmap_generated', subject: $roadmap, actor: $actor, after: [
            'engagement_id' => $engagement->getKey(),
            'roadmap_version' => $nextVersion,
            'proposal_id' => $proposal?->getKey(),
            'strategic_plan_id' => $plan->getKey(),
        ]);

        return $roadmap->refresh()->load('strategicPlan');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function agenda(FoundingAdvisoryEngagement $engagement, CarbonImmutable $start, array $input): array
    {
        $baseline = $engagement->baseline ?? [];
        $industry = trim((string) data_get($baseline, 'founding_advisory_payload.industry', 'the selected market'));
        $customer = trim((string) data_get($baseline, 'founding_advisory_payload.validated_customer', 'the priority customer'));
        $replanFocus = array_values(array_filter([
            trim((string) ($input['sales_pipeline'] ?? '')),
            trim((string) ($input['cash_funding'] ?? '')),
            trim((string) ($input['delivery_capacity'] ?? '')),
            trim((string) ($input['changed_assumptions'] ?? '')),
            trim((string) ($input['risks'] ?? '')),
            trim((string) ($input['advisor_decisions'] ?? '')),
        ]));

        return [
            'baseline_version' => (int) ($baseline['version'] ?? 1),
            'generated_from' => $input === [] ? 'Founding Baseline v1' : 'Founding Baseline v1 and a 90-day review',
            'planning_start_date' => $start->toDateString(),
            'replan_focus' => $replanFocus,
            'horizons' => [
                $this->horizon(
                    key: 'days_0_90',
                    label: 'Days 0-90',
                    commitment: 'committed',
                    start: $start,
                    fromDay: 0,
                    toDay: 90,
                    outcomes: [
                        'Establish the minimum operating foundations for launch.',
                        'Test the offer, pricing, and sales conversation with '.$customer.'.',
                        'Run a weekly cash and funding-control rhythm for the '.$industry.' launch.',
                    ],
                    milestones: [
                        $this->milestone('Confirm launch assumptions, offer, and pricing guardrails.', 'advisor', 14),
                        $this->milestone('Complete the operating setup required before trading.', 'client', 30),
                        $this->milestone('Run first-customer outreach and record the resulting evidence.', 'client', 45),
                        $this->milestone('Review early revenue, capacity, cash, and risks with the advisor.', 'joint', 75),
                    ],
                ),
                $this->horizon(
                    key: 'days_91_180',
                    label: 'Days 91-180',
                    commitment: 'provisional',
                    start: $start,
                    fromDay: 91,
                    toDay: 180,
                    outcomes: [
                        'Turn early demand into a repeatable sales rhythm.',
                        'Refine pricing and delivery capacity using the first trading evidence.',
                        'Renew the cash, funding, and risk plan before the next operating period.',
                    ],
                    milestones: [
                        $this->milestone('Prioritise the customer segments and channels that converted in the first period.', 'joint', 105),
                        $this->milestone('Document a repeatable delivery and customer follow-up rhythm.', 'client', 135),
                        $this->milestone('Review cash runway, funding assumptions, and the next 90-day agenda.', 'advisor', 165),
                    ],
                ),
                $this->horizon(
                    key: 'days_181_270',
                    label: 'Days 181-270',
                    commitment: 'indicative',
                    start: $start,
                    fromDay: 181,
                    toDay: 270,
                    outcomes: [
                        'Strengthen delivery, governance, and reporting for the next stage of trading.',
                        'Use evidence from trading to decide the next investment and growth priorities.',
                        'Prepare the transition review into Standard Advisory when meaningful operating data exists.',
                    ],
                    milestones: [
                        $this->milestone('Review the operating model against actual sales, margin, capacity, and cash evidence.', 'joint', 210),
                        $this->milestone('Agree the conditions for transition to Standard Advisory or a further founding cycle.', 'advisor', 255),
                    ],
                ),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $outcomes
     * @param  array<int, array<string, mixed>>  $milestones
     * @return array<string, mixed>
     */
    private function horizon(
        string $key,
        string $label,
        string $commitment,
        CarbonImmutable $start,
        int $fromDay,
        int $toDay,
        array $outcomes,
        array $milestones,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'commitment' => $commitment,
            'from_day' => $fromDay,
            'to_day' => $toDay,
            'starts_on' => $start->addDays($fromDay)->toDateString(),
            'ends_on' => $start->addDays($toDay)->toDateString(),
            'outcomes' => $outcomes,
            'milestones' => $milestones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function milestone(string $title, string $owner, int $dueDay): array
    {
        return [
            'title' => $title,
            'owner' => $owner,
            'due_day' => $dueDay,
        ];
    }

    private function seedCommittedMilestones(StrategicPlan $plan, FoundingRoadmapVersion $roadmap): void
    {
        if ($plan->milestones()->exists()) {
            return;
        }

        $committed = collect((array) data_get($roadmap->agenda, 'horizons', []))
            ->first(fn (mixed $horizon): bool => data_get($horizon, 'commitment') === 'committed');

        collect((array) data_get($committed, 'milestones', []))
            ->filter(fn (mixed $milestone): bool => is_array($milestone))
            ->each(function (array $milestone) use ($plan): void {
                StrategicPlanMilestone::query()->create([
                    'strategic_plan_id' => $plan->getKey(),
                    'client_id' => $plan->client_id,
                    'title' => trim((string) ($milestone['title'] ?? 'Founding roadmap milestone')),
                    'owner' => in_array($milestone['owner'] ?? null, ['client', 'advisor', 'joint'], true)
                        ? $milestone['owner']
                        : 'joint',
                    'due_offset_days' => max(1, (int) ($milestone['due_day'] ?? 30)),
                    'status' => StrategicPlanMilestone::STATUS_PENDING,
                    'progress_percent' => 0,
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $agenda
     */
    private function roadmapSummary(FoundingAdvisoryEngagement $engagement, array $agenda): string
    {
        $concept = trim((string) data_get($engagement->baseline, 'concept_summary', ''));
        $source = (string) ($agenda['generated_from'] ?? 'Founding Baseline v1');

        return trim(implode("\n\n", array_filter([
            'This rolling Founding Advisory roadmap starts from '.$source.'.',
            $concept !== '' ? 'Founder context: '.$concept : null,
            'The first 90 days are committed work. The following two horizons remain visible for planning and are revised at each review.',
        ])));
    }

    /**
     * @param  array<string, mixed>  $agenda
     * @return array<int, array{key:string,title:string,body:string}>
     */
    private function roadmapSections(array $agenda): array
    {
        return collect((array) ($agenda['horizons'] ?? []))
            ->filter(fn (mixed $horizon): bool => is_array($horizon))
            ->map(function (array $horizon): array {
                $outcomes = collect((array) ($horizon['outcomes'] ?? []))
                    ->filter(fn (mixed $outcome): bool => is_string($outcome))
                    ->map(fn (string $outcome): string => '- '.$outcome)
                    ->implode("\n");

                return [
                    'key' => (string) ($horizon['key'] ?? 'founding-horizon'),
                    'title' => (string) ($horizon['label'] ?? 'Founding horizon'),
                    'body' => trim(implode("\n\n", [
                        ucfirst((string) ($horizon['commitment'] ?? 'planned')).' agenda',
                        $outcomes,
                    ])),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function normaliseReplanInput(array $input): array
    {
        return collect([
            'reason',
            'sales_pipeline',
            'cash_funding',
            'delivery_capacity',
            'changed_assumptions',
            'risks',
            'advisor_decisions',
        ])
            ->mapWithKeys(function (string $key) use ($input): array {
                return [$key => trim((string) ($input[$key] ?? ''))];
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function changeLines(array $input): array
    {
        $labels = [
            'sales_pipeline' => 'Sales and pipeline',
            'cash_funding' => 'Cash and funding',
            'delivery_capacity' => 'Delivery capacity',
            'changed_assumptions' => 'Changed assumptions',
            'risks' => 'Risks',
            'advisor_decisions' => 'Advisor decisions',
        ];

        return collect($labels)
            ->map(function (string $label, string $key) use ($input): ?string {
                $value = trim((string) ($input[$key] ?? ''));

                return $value === '' ? null : $label.': '.$value;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseline(
        EntrepreneurProfile $profile,
        BusinessPlan $plan,
        ?PlanAssessment $assessment,
        ?AdvisoryReadinessSignal $signal,
    ): array {
        return [
            'version' => 1,
            'captured_at' => now()->toIso8601String(),
            'concept_summary' => $profile->concept_summary,
            'founding_advisory_payload' => $plan->founding_advisory_payload ?? [],
            'business_plan_snapshot' => $assessment?->plan_snapshot ?? $this->snapshots->capture($plan),
            'assessment' => $assessment instanceof PlanAssessment ? [
                'id' => $assessment->getKey(),
                'round' => $assessment->round,
                'overall_grade' => $assessment->overall_grade,
                'mentor_notes' => $assessment->mentor_notes ?? [],
            ] : null,
            'advisory_readiness' => $signal instanceof AdvisoryReadinessSignal ? [
                'id' => $signal->getKey(),
                'score' => $signal->score,
                'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function advisorFor(FoundingAdvisoryEngagement $engagement, User $fallback): User
    {
        $preparedBy = $engagement->preparedBy;

        return $preparedBy instanceof User ? $preparedBy : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FoundingAdvisoryEngagement $engagement, bool $forAdvisor): array
    {
        $versions = $engagement->roadmapVersions
            ->sortByDesc('version')
            ->values();
        $current = $versions->firstWhere('status', FoundingRoadmapVersion::STATUS_PUBLISHED);
        $draft = $versions->firstWhere('status', FoundingRoadmapVersion::STATUS_DRAFT);

        return [
            'id' => $engagement->getKey(),
            'status' => $engagement->status,
            'status_label' => str($engagement->status)->replace('_', ' ')->title()->toString(),
            'baseline' => [
                'version' => (int) data_get($engagement->baseline, 'version', 1),
                'captured_at' => data_get($engagement->baseline, 'captured_at'),
                'concept_summary' => data_get($engagement->baseline, 'concept_summary'),
                'assessment_score' => data_get($engagement->baseline, 'advisory_readiness.score'),
                'assessment_grade' => data_get($engagement->baseline, 'assessment.overall_grade'),
                'plan_title' => data_get($engagement->baseline, 'business_plan_snapshot.business_plan.title'),
                'issue_readiness' => data_get($engagement->baseline, 'business_plan_snapshot.issue_readiness'),
                'budget' => data_get($engagement->baseline, 'business_plan_snapshot.budget'),
            ],
            'accepted_at' => $engagement->accepted_at?->toIso8601String(),
            'started_at' => $engagement->started_at?->toIso8601String(),
            'replan_due_at' => $engagement->replan_due_at?->toIso8601String(),
            'replan_due' => $engagement->replan_due_at?->isPast() ?? false,
            'transition_review_at' => $engagement->transition_review_at?->toIso8601String(),
            'current_version' => $current instanceof FoundingRoadmapVersion ? $this->versionPayload($current, $forAdvisor) : null,
            'draft_version' => $draft instanceof FoundingRoadmapVersion ? $this->versionPayload($draft, $forAdvisor) : null,
            'versions' => $versions
                ->take(12)
                ->map(fn (FoundingRoadmapVersion $version): array => $this->versionPayload($version, $forAdvisor))
                ->all(),
            'can_replan' => $forAdvisor && $current instanceof FoundingRoadmapVersion,
            'replan_url' => $forAdvisor
                ? route('advisor.founding-advisory.replan', $engagement, absolute: false)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(FoundingRoadmapVersion $version, bool $forAdvisor): array
    {
        return [
            'id' => $version->getKey(),
            'version' => $version->version,
            'status' => $version->status,
            'status_label' => str($version->status)->replace('_', ' ')->title()->toString(),
            'planning_start_date' => $version->planning_start_date?->toDateString(),
            'planning_through_date' => $version->planning_through_date?->toDateString(),
            'agenda' => $version->agenda ?? [],
            'replan_input' => $version->replan_input ?? [],
            'change_summary' => $version->change_summary ?? [],
            'generated_at' => $version->generated_at?->toIso8601String(),
            'published_at' => $version->published_at?->toIso8601String(),
            'strategic_plan_id' => $version->strategic_plan_id,
            'publish_url' => $forAdvisor && $version->status === FoundingRoadmapVersion::STATUS_DRAFT
                ? route('advisor.founding-advisory.roadmaps.publish', [$version->founding_advisory_engagement_id, $version], absolute: false)
                : null,
        ];
    }
}
