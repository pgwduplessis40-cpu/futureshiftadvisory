<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ReportType;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\MessageThread;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\PlanIssueReadiness;
use App\Services\Entrepreneurs\PlanRequirements;

/**
 * @phpstan-type PayloadScalar bool|float|int|string|null
 * @phpstan-type PayloadLeaf array<array-key, PayloadScalar>
 * @phpstan-type PayloadBranch array<array-key, PayloadScalar|PayloadLeaf>
 * @phpstan-type PayloadValue PayloadScalar|PayloadLeaf|PayloadBranch|array<array-key, PayloadBranch>
 */
final class EntrepreneurPlanWorkspacePayload
{
    public const READINESS_FIELDS = [
        'concept_clarity' => 'Concept clarity',
        'customer_need' => 'Customer need',
        'evidence_strength' => 'Evidence strength',
        'industry_experience' => 'Industry experience',
        'personal_capacity' => 'Personal capacity',
        'financial_runway' => 'Financial runway',
        'support_network' => 'Support network',
        'launch_readiness' => 'Launch readiness',
    ];

    private const GAMIFICATION_DISABLE_REQUEST_SUBJECT = 'Gamification disable request';

    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanRequirements $requirements,
        private readonly PlanIssueReadiness $planIssueReadiness,
        private readonly BusinessPlanExecutiveSummary $executiveSummaries,
        private readonly EntrepreneurGamification $gamification,
        private readonly AdvisoryReadiness $advisoryReadiness,
    ) {}

    /** @return array<string, PayloadValue> */
    public function show(EntrepreneurProfile $profile): array
    {
        $plan = $this->workspace->latestPlan($profile);
        $packageAccess = $this->workspace->packageAccess($profile);

        return [
            'profile' => $this->profile($profile),
            'packageAccess' => $packageAccess,
            'readiness' => $this->readiness($profile),
            'readinessFields' => collect(self::READINESS_FIELDS)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'ideaValidation' => $this->ideaValidation($profile),
            'ideaValidationVersions' => $this->ideaValidationVersions($profile),
            'plan' => $plan instanceof BusinessPlan ? $this->plan($plan) : null,
            'planTemplate' => $this->planTemplate(),
            'reports' => $this->reports($profile),
            'advisoryRequest' => $this->advisoryRequest($profile, $plan),
            'gamification' => $this->gamification($profile, $plan),
            'urls' => [
                'dashboard' => route('portal.entrepreneur.dashboard', absolute: false),
                'readiness' => route('portal.entrepreneur.readiness.store', absolute: false),
                'ideaValidation' => route('portal.entrepreneur.idea-validation.store', absolute: false),
                'recallIdeaValidation' => route('portal.entrepreneur.idea-validation.recall', absolute: false),
                'startPlan' => route('portal.entrepreneur.plan.start', absolute: false),
                'companyNameUpdate' => route('portal.entrepreneur.plan.company-name.update', absolute: false),
                'sectionStore' => route('portal.entrepreneur.plan.sections.store', absolute: false),
                'budgetUpdate' => route('portal.entrepreneur.plan.budget.update', absolute: false),
                'budgetPack' => route('portal.entrepreneur.plan.budget-pack.show', absolute: false),
                'budgetPackPdf' => route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false),
                'budgetFlagAcknowledge' => route('portal.entrepreneur.plan.budget.flags.acknowledge', absolute: false),
                'budgetAdvisorNudgeDismiss' => route('portal.entrepreneur.plan.budget.advisor-nudge.dismiss', absolute: false),
                'assistRequirement' => route('portal.entrepreneur.plan.requirements.assist', absolute: false),
                'executiveSummary' => route('portal.entrepreneur.plan.executive-summary.store', absolute: false),
                'preview' => route('portal.entrepreneur.plan.preview', absolute: false),
                'submit' => route('portal.entrepreneur.plan.submit', absolute: false),
                'documentUpload' => route('portal.documents.store', absolute: false),
                'messages' => route('portal.messages.index', absolute: false),
                'advisoryRequest' => route('portal.entrepreneur.advisory-request.store', absolute: false),
            ],
        ];
    }

    /** @return array<string, PayloadValue> */
    private function profile(EntrepreneurProfile $profile): array
    {
        $stage = $profile->currentStage();

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'company_name' => $profile->company_name,
            'email' => $profile->email,
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'concept_summary' => $profile->concept_summary,
        ];
    }

    /** @return array<string, PayloadValue> */
    private function readiness(EntrepreneurProfile $profile): array
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
            'personal_barriers' => $assessment instanceof ReadinessAssessment ? $assessment->personal_barriers : [],
        ];
    }

    /** @return array<string, PayloadValue>|null */
    private function ideaValidation(EntrepreneurProfile $profile): ?array
    {
        $validation = $this->latestIdeaValidation($profile);

        if (! $validation instanceof IdeaValidation) {
            return null;
        }

        return [
            'id' => $validation->id,
            'revision_number' => $validation->revision_number,
            'problem' => $validation->problem,
            'target_customer' => $validation->target_customer,
            'solution' => $validation->solution,
            'value_proposition' => $validation->value_proposition,
            'demand_signal' => $validation->demand_signal,
            'revenue_model' => $validation->revenue_model,
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'viability_alerts' => $validation->viability_alerts ?? [],
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'advisor_gate_status' => $this->ideaGateStatus($validation),
            'change_request_note' => data_get($validation->ai_evaluation, 'metadata.change_request_note'),
            'changes_requested_at' => data_get($validation->ai_evaluation, 'metadata.changes_requested_at'),
            'recalled_at' => $validation->recalled_at?->toIso8601String(),
            'restored_from_revision_number' => data_get($validation->ai_evaluation, 'metadata.restored_from_revision_number'),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'plan_builder_unlocked' => $validation->advisor_gate_passed_at !== null,
        ];
    }

    /** @return list<array<string, PayloadValue>> */
    private function ideaValidationVersions(EntrepreneurProfile $profile): array
    {
        $latestId = $this->latestIdeaValidation($profile)?->getKey();

        return IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->get()
            ->map(function (IdeaValidation $validation) use ($latestId): array {
                return [
                    'id' => $validation->id,
                    'revision_number' => $validation->revision_number,
                    'problem' => $validation->problem,
                    'target_customer' => $validation->target_customer,
                    'solution' => $validation->solution,
                    'value_proposition' => $validation->value_proposition,
                    'demand_signal' => $validation->demand_signal,
                    'revenue_model' => $validation->revenue_model,
                    'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
                    'advisor_gate_status' => $this->ideaGateStatus($validation),
                    'recalled_at' => $validation->recalled_at?->toIso8601String(),
                    'is_current' => (string) $validation->getKey() === (string) $latestId,
                    'restore_url' => route('portal.entrepreneur.idea-validation.restore', $validation, absolute: false),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<array-key, mixed> */
    private function plan(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections', 'assessments.ratingFramework.criteria', 'budgetRunway');
        $phasesByKey = $plan->phases->keyBy('key');
        $requirements = $this->requirements->payload($plan);
        $completion = $this->requirements->completion($plan, $requirements);
        $latestAssessment = $plan->assessments->sortByDesc('round')->first();

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'completed_at' => $plan->completed_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'requirements_complete' => $completion['complete'],
            'missing_requirements' => $completion['missing'],
            'external_issue_readiness' => $this->planIssueReadiness->evaluate($plan),
            'executive_summary' => $this->executiveSummaries->status($plan),
            'budget' => $this->requirements->budgetPayload($plan, $plan->budgetRunway),
            'latest_assessment' => $latestAssessment ? [
                'id' => $latestAssessment->id,
                'round' => $latestAssessment->round,
                'status' => $latestAssessment->finalised_at === null ? 'in_review' : 'completed',
                'overall_grade' => $latestAssessment->overall_grade,
                'finalised_at' => $latestAssessment->finalised_at?->toIso8601String(),
                'url' => route('portal.entrepreneur.assessments.show', $latestAssessment, absolute: false),
            ] : null,
            'phases' => collect(PlanRequirements::definitions())
                ->map(function (array $definition, string $phaseKey) use ($phasesByKey, $requirements): array {
                    $phase = $phasesByKey->get($phaseKey);

                    return [
                        'id' => $phase instanceof PlanPhase ? (string) $phase->id : $phaseKey,
                        'key' => $phaseKey,
                        'title' => $phase instanceof PlanPhase ? (string) $phase->title : $definition['title'],
                        'status' => $phase instanceof PlanPhase ? (string) $phase->status : 'pending',
                        'requirements' => $requirements[$phaseKey] ?? [],
                        'sections' => $phase instanceof PlanPhase ? $phase->sections
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
                                'updated_at' => $section->updated_at?->toIso8601String(),
                                'guidance_url' => route('portal.entrepreneur.plan.sections.guidance', $section, absolute: false),
                            ])
                            ->values()
                            ->all() : [],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /** @return list<array<string, PayloadValue>> */
    private function planTemplate(): array
    {
        return collect(PlanRequirements::definitions())
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

    /** @return list<array<string, PayloadValue>> */
    private function reports(EntrepreneurProfile $profile): array
    {
        return Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function (Report $report): array {
                $url = route('portal.reports.show', $report, absolute: false);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'type' => $report->type->value,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'view_url' => $url,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, PayloadValue> */
    private function advisoryRequest(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signal = $this->advisoryReadiness->currentSignalForPlan($plan);
        $thread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('subject', EntrepreneurPlanWorkspace::ADVISORY_REQUEST_SUBJECT)
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

    /** @return array<string, PayloadValue> */
    private function gamification(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
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

    private function ideaGateStatus(IdeaValidation $validation): string
    {
        if ($validation->recalled_at !== null) {
            return 'recalled';
        }

        if ($validation->advisor_gate_passed_at !== null) {
            return 'approved';
        }

        $status = data_get($validation->ai_evaluation, 'metadata.advisor_gate_status');

        return is_string($status) && trim($status) !== '' ? $status : 'advisor_review';
    }

    private function latestIdeaValidation(EntrepreneurProfile $profile): ?IdeaValidation
    {
        return IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->first();
    }
}
