<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\MessageThread;
use App\Models\PlanSection;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Services\Entrepreneurs\Readiness;
use App\Services\Messaging\MessageThreadService;
use App\Services\Pdf\PdfRenderer;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class EntrepreneurPlanController extends Controller
{
    private const ADVISORY_REQUEST_SUBJECT = 'Advisory conversion request';

    private const GAMIFICATION_DISABLE_REQUEST_SUBJECT = 'Gamification disable request';

    private const READINESS_FIELDS = [
        'concept_clarity' => 'Concept clarity',
        'customer_need' => 'Customer need',
        'evidence_strength' => 'Evidence strength',
        'industry_experience' => 'Industry experience',
        'personal_capacity' => 'Personal capacity',
        'financial_runway' => 'Financial runway',
        'support_network' => 'Support network',
        'launch_readiness' => 'Launch readiness',
    ];

    private const PLAN_REQUIREMENTS = [
        'foundation' => [
            'title' => 'Foundation',
            'requirements' => [
                [
                    'key' => 'business-type-location',
                    'title' => 'Business type, location, and operating model',
                    'description' => 'Describe the type of business, location, and means of doing business.',
                ],
                [
                    'key' => 'mission-vision',
                    'title' => 'Mission and vision',
                    'description' => 'Explain the mission, vision, and the problem the business exists to solve.',
                ],
            ],
        ],
        'market' => [
            'title' => 'Market',
            'requirements' => [
                [
                    'key' => 'industry-context',
                    'title' => 'Industry and customer demand',
                    'description' => 'Discuss the industry, customer segment, demand evidence, and market timing.',
                ],
                [
                    'key' => 'differentiation',
                    'title' => 'What sets the business apart',
                    'description' => 'Describe competitors, alternatives, and why customers would choose this business.',
                ],
            ],
        ],
        'strategy' => [
            'title' => 'Strategy',
            'requirements' => [
                [
                    'key' => 'success-factors',
                    'title' => 'Unique success factors',
                    'description' => 'Describe the capabilities, relationships, or assets that improve the chance of success.',
                ],
                [
                    'key' => 'goals-objectives',
                    'title' => 'Goals and objectives',
                    'description' => 'Set the launch goals, milestones, decisions, and measures of success.',
                ],
                [
                    'key' => 'culture',
                    'title' => 'Culture',
                    'description' => 'Explain the team culture, values, operating behaviours, and customer promise.',
                ],
            ],
        ],
        'legal_operations' => [
            'title' => 'Legal & Operations',
            'requirements' => [
                [
                    'key' => 'intellectual-property',
                    'title' => 'Intellectual property',
                    'description' => 'Identify brand, data, methods, contracts, licences, or IP that need protection.',
                ],
                [
                    'key' => 'legal-environment',
                    'title' => 'Legal environment',
                    'description' => 'List legal, privacy, compliance, supplier, employment, or industry obligations.',
                ],
            ],
        ],
        'financial' => [
            'title' => 'Financial',
            'requirements' => [
                [
                    'key' => 'revenue-model',
                    'title' => 'Revenue model',
                    'description' => 'Explain pricing, margin, cost drivers, cash cycle, and early revenue assumptions.',
                ],
                [
                    'key' => 'launch-funding',
                    'title' => 'Launch funding and support',
                    'description' => 'Describe start-up funding, support needed, runway, and financial risk controls.',
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly Readiness $readiness,
        private readonly IdeaValidationService $ideas,
        private readonly PlanBuilder $plans,
        private readonly SharedPlanBuilder $sharedPlans,
        private readonly Guidance $guidance,
        private readonly PlanDocuments $documents,
        private readonly MessageThreadService $messages,
        private readonly PdfRenderer $pdf,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
        private readonly EntrepreneurGamification $gamification,
    ) {}

    public function show(Request $request): Response
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);

        return Inertia::render('portal/entrepreneur/Plan', [
            'profile' => $this->profilePayload($profile),
            'readiness' => $this->readinessPayload($profile),
            'readinessFields' => collect(self::READINESS_FIELDS)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'ideaValidation' => $this->ideaValidationPayload($profile),
            'plan' => $plan instanceof BusinessPlan ? $this->planPayload($plan) : null,
            'planTemplate' => $this->planTemplatePayload(),
            'reports' => $this->reportPayloads($profile),
            'advisoryRequest' => $this->advisoryRequestPayload($profile, $plan),
            'gamification' => $this->gamificationPayload($profile, $plan),
            'urls' => [
                'dashboard' => route('portal.entrepreneur.dashboard', absolute: false),
                'readiness' => route('portal.entrepreneur.readiness.store', absolute: false),
                'ideaValidation' => route('portal.entrepreneur.idea-validation.store', absolute: false),
                'startPlan' => route('portal.entrepreneur.plan.start', absolute: false),
                'sectionStore' => route('portal.entrepreneur.plan.sections.store', absolute: false),
                'preview' => route('portal.entrepreneur.plan.preview', absolute: false),
                'submit' => route('portal.entrepreneur.plan.submit', absolute: false),
                'documentUpload' => route('portal.documents.store', absolute: false),
                'messages' => route('portal.messages.index', absolute: false),
                'advisoryRequest' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            ],
        ]);
    }

    public function preview(Request $request): SymfonyResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);
        $phases = $plan instanceof BusinessPlan
            ? $this->planPayload($plan)['phases']
            : $this->templatePreviewPhases();

        $pdf = $this->pdf->render($this->previewHtml($profile, $plan, $phases));
        $filename = Str::slug($profile->name ?: 'entrepreneur-business-plan').'-preview.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function readiness(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);

        $rules = collect(array_keys(self::READINESS_FIELDS))
            ->mapWithKeys(fn (string $field): array => [$field => ['required', 'numeric', 'min:0', 'max:5']])
            ->all();
        $validated = $request->validate([
            ...$rules,
            'personal_barriers' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->readiness->assess($profile, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-readiness-saved');
    }

    public function ideaValidation(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $validated = $request->validate([
            'problem' => ['required', 'string', 'min:20', 'max:1200'],
            'target_customer' => ['required', 'string', 'min:20', 'max:1200'],
            'solution' => ['required', 'string', 'min:20', 'max:1200'],
            'value_proposition' => ['required', 'string', 'min:20', 'max:1200'],
            'demand_signal' => ['required', 'string', 'min:20', 'max:1200'],
            'revenue_model' => ['required', 'string', 'min:20', 'max:1200'],
        ]);

        $this->ideas->evaluate($profile, $validated, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-idea-submitted');
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);

        try {
            $this->plans->start($profile, $user);
        } catch (InvalidArgumentException $exception) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-plan-locked')
                ->with('entrepreneur_plan_error', $exception->getMessage());
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-started');
    }

    public function section(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $validated = $request->validate([
            'phase_key' => ['required', 'string', Rule::in(array_keys(self::PLAN_REQUIREMENTS))],
            'requirement_key' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:80', 'max:8000'],
            'attached_document_ids' => ['array'],
            'attached_document_ids.*' => ['string', 'uuid'],
        ]);

        $phaseKey = (string) $validated['phase_key'];
        $requirementKey = (string) $validated['requirement_key'];
        $requirement = $this->requirement($phaseKey, $requirementKey);

        $section = $this->plans->upsertSection(
            plan: $plan,
            phaseKey: $phaseKey,
            key: 'founder-'.$phaseKey.'-'.$requirementKey,
            title: (string) ($validated['title'] ?: $requirement['title']),
            body: (string) $validated['body'],
            actor: $user,
            metadata: [
                'source' => 'entrepreneur_plan_workspace',
                'requirement_key' => $requirementKey,
                'requirement_title' => $requirement['title'],
                'completed_by_user_id' => $user->getKey(),
            ],
        );

        foreach (array_values((array) ($validated['attached_document_ids'] ?? [])) as $documentId) {
            $document = Document::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->whereKey($documentId)
                ->first();

            if ($document instanceof Document) {
                $this->documents->attachAndVerify(
                    section: $section,
                    document: $document,
                    actor: $user,
                    claim: Str::limit((string) $validated['body'], 500, ''),
                );
            }
        }

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-section-saved');
    }

    public function guidance(Request $request, PlanSection $planSection): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $this->assertSectionBelongsToProfile($planSection, $profile);

        $this->guidance->guide($planSection, $user);

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-guidance-generated');
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        $completion = $this->requirementsCompletion($plan);
        if (! $completion['complete']) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-plan-requirements-missing')
                ->with('entrepreneur_plan_missing_requirements', $completion['missing']);
        }

        $plan->forceFill([
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'submitted_at' => $plan->submitted_at ?? now(),
            'founding_advisory_payload' => $this->sharedPlans->foundingPayload($plan),
        ])->save();
        $profile->forceFill(['stage' => EntrepreneurStage::SUBMITTED])->save();

        $this->audit->record('entrepreneur.plan_submitted', subject: $plan, actor: $user, after: [
            'entrepreneur_profile_id' => $profile->getKey(),
            'completed_requirements' => $completion['completed'],
        ]);
        $this->milestones->awardPlanSubmitted($plan->refresh()->load('entrepreneurProfile'));

        return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-plan-submitted');
    }

    public function requestAdvisory(Request $request): RedirectResponse
    {
        $user = $this->entrepreneurUser($request);
        $profile = $this->profileFor($user);
        $plan = $this->latestPlan($profile);
        $signal = $this->latestReadinessSignal($profile);

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-not-ready');
        }

        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::ADVISORY_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        if (! $thread instanceof MessageThread) {
            $message = $this->messages->startEntrepreneurThread(
                profile: $profile,
                sender: $user,
                subject: self::ADVISORY_REQUEST_SUBJECT,
                body: sprintf(
                    'I would like to request standard advisory support using my entrepreneur business plan%s.',
                    $plan instanceof BusinessPlan ? ' ('.$plan->title.')' : '',
                ),
            );
            $thread = $message->thread;
        }

        $this->audit->record('entrepreneur.advisory_requested', subject: $profile, actor: $user, after: [
            'business_plan_id' => $plan?->getKey(),
            'advisory_readiness_signal_id' => $signal->getKey(),
            'message_thread_id' => $thread?->getKey(),
        ]);

        return $thread instanceof MessageThread
            ? to_route('portal.messages.show', $thread)->with('status', 'entrepreneur-advisory-requested')
            : to_route('portal.entrepreneur.plan.show')->with('status', 'entrepreneur-advisory-requested');
    }

    private function entrepreneurUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        return $user;
    }

    private function profileFor(User $user): EntrepreneurProfile
    {
        return EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    private function latestPlan(EntrepreneurProfile $profile): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with('phases.sections', 'assessments.ratingFramework.criteria')
            ->latest('updated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(EntrepreneurProfile $profile): array
    {
        $stage = $profile->stage instanceof EntrepreneurStage
            ? $profile->stage
            : EntrepreneurStage::from((string) $profile->stage);

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'email' => $profile->email,
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'concept_summary' => $profile->concept_summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readinessPayload(EntrepreneurProfile $profile): array
    {
        $assessment = ReadinessAssessment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('assessed_at')
            ->latest()
            ->first();

        return [
            'completed' => $assessment instanceof ReadinessAssessment,
            'score' => $assessment?->score,
            'outcome' => $assessment?->outcome,
            'assessed_at' => $assessment?->assessed_at?->toIso8601String(),
            'personal_barriers' => $assessment?->personal_barriers ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ideaValidationPayload(EntrepreneurProfile $profile): ?array
    {
        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('evaluated_at')
            ->latest()
            ->first();

        if (! $validation instanceof IdeaValidation) {
            return null;
        }

        return [
            'id' => $validation->id,
            'problem' => $validation->problem,
            'target_customer' => $validation->target_customer,
            'solution' => $validation->solution,
            'value_proposition' => $validation->value_proposition,
            'demand_signal' => $validation->demand_signal,
            'revenue_model' => $validation->revenue_model,
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'viability_alerts' => $validation->viability_alerts ?? [],
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'plan_builder_unlocked' => $validation->advisor_gate_passed_at !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections', 'assessments.ratingFramework.criteria');
        $phasesByKey = $plan->phases->keyBy('key');
        $requirements = $this->requirementsPayload($plan);
        $completion = $this->requirementsCompletion($plan, $requirements);
        $latestAssessment = $plan->assessments->sortByDesc('round')->first();

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'completed_at' => $plan->completed_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'requirements_complete' => $completion['complete'],
            'missing_requirements' => $completion['missing'],
            'latest_assessment' => $latestAssessment ? [
                'id' => $latestAssessment->id,
                'round' => $latestAssessment->round,
                'status' => $latestAssessment->finalised_at === null ? 'in_review' : 'completed',
                'overall_grade' => $latestAssessment->overall_grade,
                'finalised_at' => $latestAssessment->finalised_at?->toIso8601String(),
                'url' => route('portal.entrepreneur.assessments.show', $latestAssessment, absolute: false),
            ] : null,
            'phases' => collect(self::PLAN_REQUIREMENTS)
                ->map(function (array $definition, string $phaseKey) use ($phasesByKey, $requirements): array {
                    $phase = $phasesByKey->get($phaseKey);

                    return [
                        'id' => (string) ($phase?->id ?? $phaseKey),
                        'key' => $phaseKey,
                        'title' => (string) ($phase?->title ?? $definition['title']),
                        'status' => (string) ($phase?->status ?? 'pending'),
                        'requirements' => $requirements[$phaseKey] ?? [],
                        'sections' => $phase?->sections
                            ->sortBy('created_at')
                            ->map(fn (PlanSection $section): array => [
                                'id' => $section->id,
                                'title' => $section->title,
                                'body' => $section->body,
                                'source_type' => $section->source_type,
                                'completeness_status' => $section->completeness_status,
                                'attached_document_ids' => $section->attached_document_ids ?? [],
                                'predictive_score' => $section->predictive_score,
                                'guidance' => data_get($section->metadata, 'ai_guidance'),
                                'requirement_key' => data_get($section->metadata, 'requirement_key'),
                                'guidance_url' => route('portal.entrepreneur.plan.sections.guidance', $section, absolute: false),
                            ])
                            ->values()
                            ->all() ?? [],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planTemplatePayload(): array
    {
        return collect(self::PLAN_REQUIREMENTS)
            ->map(fn (array $definition, string $phaseKey): array => [
                'key' => $phaseKey,
                'title' => $definition['title'],
                'requirements' => collect($definition['requirements'])
                    ->map(fn (array $requirement): array => [
                        ...$requirement,
                        'phase_key' => $phaseKey,
                        'phase_title' => $definition['title'],
                        'complete' => false,
                        'section_id' => null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function requirementsPayload(BusinessPlan $plan): array
    {
        $sections = $plan->sections;

        return collect(self::PLAN_REQUIREMENTS)
            ->mapWithKeys(function (array $definition, string $phaseKey) use ($sections): array {
                return [
                    $phaseKey => collect($definition['requirements'])
                        ->map(function (array $requirement) use ($phaseKey, $definition, $sections): array {
                            $section = $sections->first(fn (PlanSection $candidate): bool => (
                                (string) data_get($candidate->metadata, 'requirement_key') === $requirement['key']
                                || $candidate->key === 'founder-'.$phaseKey.'-'.$requirement['key']
                            ));

                            return [
                                ...$requirement,
                                'phase_key' => $phaseKey,
                                'phase_title' => $definition['title'],
                                'complete' => $section instanceof PlanSection
                                    && $section->completeness_status === PlanSection::STATUS_COMPLETE,
                                'section_id' => $section?->id,
                                'section_title' => $section?->title,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>|null  $requirements
     * @return array{complete:bool,missing:array<int, string>,completed:array<int, string>}
     */
    private function requirementsCompletion(BusinessPlan $plan, ?array $requirements = null): array
    {
        $requirements ??= $this->requirementsPayload($plan);
        $flattened = collect($requirements)->flatMap(fn (array $rows): array => $rows)->values();
        $missing = $flattened
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => $requirement['phase_title'].': '.$requirement['title'])
            ->values()
            ->all();

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'completed' => $flattened
                ->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templatePreviewPhases(): array
    {
        return collect($this->planTemplatePayload())
            ->map(fn (array $phase): array => [
                'id' => $phase['key'],
                'key' => $phase['key'],
                'title' => $phase['title'],
                'status' => 'pending',
                'requirements' => $phase['requirements'],
                'sections' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportPayloads(EntrepreneurProfile $profile): array
    {
        return Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'type' => $report->type->value,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'download_url' => route('portal.reports.show', $report, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function advisoryRequestPayload(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signal = $this->latestReadinessSignal($profile);
        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::ADVISORY_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        return [
            'available' => $signal instanceof AdvisoryReadinessSignal && ! ($thread instanceof MessageThread) && ! $plan?->client_id,
            'requested' => $thread instanceof MessageThread,
            'request_url' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            'thread_url' => $thread instanceof MessageThread ? route('portal.messages.show', $thread, absolute: false) : null,
            'blockers' => $signal instanceof AdvisoryReadinessSignal ? [] : ['Finalised advisory readiness is not available yet.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gamificationPayload(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', self::GAMIFICATION_DISABLE_REQUEST_SUBJECT)
            ->latest('last_activity_at')
            ->first();

        return [
            ...$this->gamification->payload($profile, $plan instanceof BusinessPlan ? $plan : null),
            'disable_request_url' => route('portal.entrepreneur.gamification.disable-request', absolute: false),
            'disable_request_requested' => $thread instanceof MessageThread,
            'disable_request_thread_url' => $thread instanceof MessageThread
                ? route('portal.messages.show', $thread, absolute: false)
                : null,
        ];
    }

    private function latestReadinessSignal(EntrepreneurProfile $profile): ?AdvisoryReadinessSignal
    {
        return AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function requirement(string $phaseKey, string $requirementKey): array
    {
        $requirement = collect(self::PLAN_REQUIREMENTS[$phaseKey]['requirements'] ?? [])
            ->first(fn (array $definition): bool => $definition['key'] === $requirementKey);
        abort_unless(is_array($requirement), 422);

        return $requirement;
    }

    private function assertSectionBelongsToProfile(PlanSection $section, EntrepreneurProfile $profile): void
    {
        $section->loadMissing('businessPlan');
        $plan = $section->businessPlan;

        abort_unless(
            $plan instanceof BusinessPlan
            && $plan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR
            && (string) $plan->entrepreneur_profile_id === (string) $profile->getKey(),
            404,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function previewHtml(EntrepreneurProfile $profile, ?BusinessPlan $plan, array $phases): string
    {
        $requirements = collect($phases)->flatMap(fn (array $phase): array => $phase['requirements'] ?? []);
        $total = $requirements->count();
        $completed = $requirements->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))->count();
        $missingHtml = $requirements
            ->reject(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->map(fn (array $requirement): string => '<li>'.$this->escape(($requirement['phase_title'] ?? 'Plan').': '.$requirement['title']).'</li>')
            ->implode('');
        $phaseHtml = collect($phases)
            ->map(fn (array $phase): string => $this->previewPhaseHtml($phase))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.brand p { margin: 0; }
.summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
.metric { border: 1px solid #d8e2dc; padding: 10px; }
.metric span { color: #667085; display: block; font-size: 10px; text-transform: uppercase; }
.metric strong { display: block; font-size: 14px; margin-top: 3px; }
.section { border: 1px solid #d8e2dc; margin-bottom: 14px; padding: 12px; break-inside: avoid; }
.section h2 { color: #214f44; font-size: 15px; margin: 0 0 8px; }
.requirement { border-top: 1px solid #edf2ef; padding: 10px 0; }
.requirement:first-of-type { border-top: 0; padding-top: 0; }
.requirement h3 { font-size: 13px; margin: 0; }
.status { border-radius: 999px; display: inline-block; font-size: 10px; margin-left: 6px; padding: 2px 7px; }
.complete { background: #e8f5ef; color: #176b4d; }
.pending { background: #fff7e6; color: #945a00; }
.body { margin-top: 6px; white-space: pre-wrap; }
.note { color: #667085; font-size: 10px; margin: 5px 0 0; }
.missing { background: #fffaf0; border: 1px solid #f3d08f; margin-bottom: 16px; padding: 10px 12px; }
.missing h2 { font-size: 13px; margin: 0 0 6px; }
.missing ul { margin: 0; padding-left: 18px; }
</style>
</head>
<body>
<header class="brand">
<h1>Business plan preview</h1>
<p>Future Shift Advisory</p>
<p>%s</p>
<p>Generated %s</p>
</header>
<section class="summary">%s%s%s</section>
%s
%s
</body>
</html>
HTML,
            $this->escape('Business plan preview - '.$profile->name),
            $this->escape($profile->name),
            $this->escape(now()->format('M j, Y g:i A')),
            $this->previewMetricHtml('Plan status', $this->formatLabel($plan?->status ?? 'not started')),
            $this->previewMetricHtml('Requirements', "{$completed}/{$total} complete"),
            $this->previewMetricHtml('Stage', $this->formatLabel($profile->stage instanceof EntrepreneurStage ? $profile->stage->value : (string) $profile->stage)),
            $missingHtml === '' ? '' : '<section class="missing"><h2>Open items before finalising</h2><ul>'.$missingHtml.'</ul></section>',
            $phaseHtml,
        );
    }

    private function previewMetricHtml(string $label, string $value): string
    {
        return sprintf(
            '<div class="metric"><span>%s</span><strong>%s</strong></div>',
            $this->escape($label),
            $this->escape($value),
        );
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function previewPhaseHtml(array $phase): string
    {
        $sections = collect($phase['sections'] ?? []);
        $requirementHtml = collect($phase['requirements'] ?? [])
            ->map(fn (array $requirement): string => $this->previewRequirementHtml($requirement, $sections))
            ->implode('');

        return sprintf(
            '<section class="section"><h2>%s</h2>%s</section>',
            $this->escape((string) $phase['title']),
            $requirementHtml,
        );
    }

    private function previewRequirementHtml(array $requirement, Collection $sections): string
    {
        $section = $sections->first(fn (array $candidate): bool => (string) ($candidate['requirement_key'] ?? '') === (string) $requirement['key']);
        $complete = (bool) ($requirement['complete'] ?? false);
        $content = is_array($section)
            ? $this->previewSectionContentHtml($section)
            : '<p class="body">Pending input: '.$this->escape((string) $requirement['description']).'</p>';

        return sprintf(
            '<article class="requirement"><h3>%s <span class="status %s">%s</span></h3>%s</article>',
            $this->escape((string) $requirement['title']),
            $complete ? 'complete' : 'pending',
            $complete ? 'Complete' : 'Pending',
            $content,
        );
    }

    private function previewSectionContentHtml(array $section): string
    {
        $documentCount = count((array) ($section['attached_document_ids'] ?? []));
        $docs = $documentCount === 1 ? '1 supporting document' : "{$documentCount} supporting documents";

        return sprintf(
            '<div class="body">%s</div><p class="note">Evidence: %s.</p>',
            nl2br($this->escape((string) ($section['body'] ?? ''))),
            $this->escape($docs),
        );
    }

    private function formatLabel(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
