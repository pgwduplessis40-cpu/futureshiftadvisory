<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Security\InviteIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly AdvisorEntrepreneurCapacity $capacity,
        private readonly AuditWriter $auditWriter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EntrepreneurProfile::class);

        $user = $this->actor($request);

        return Inertia::render('advisor/entrepreneurs/Index', [
            'entrepreneurs' => $this->visibleProfiles($user)
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (EntrepreneurProfile $profile): array => $this->profileSummary($profile))
                ->values(),
            'capacity' => $this->capacity->summary($user),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        return Inertia::render('advisor/entrepreneurs/Create', [
            'capacity' => $this->capacity->summary($this->actor($request)),
        ]);
    }

    public function store(Request $request, InviteIssuer $issuer): RedirectResponse
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        $advisor = $this->actor($request);
        $this->capacity->ensureCanAdd($advisor);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('entrepreneur_profiles', 'email'),
            ],
            'concept_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        $email = (string) $validated['email'];

        $profile = DB::transaction(function () use ($advisor, $email, $issuer, $validated): EntrepreneurProfile {
            $issued = $issuer->issue(
                email: $email,
                targetUserType: User::TYPE_ENTREPRENEUR,
                targetRole: User::TYPE_ENTREPRENEUR,
                issuedBy: $advisor,
            );

            $profile = EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
                'name' => $validated['name'],
                'email' => $email,
                'stage' => EntrepreneurStage::INVITED,
                'concept_summary' => $validated['concept_summary'] ?? null,
            ]);

            $this->auditWriter->record('entrepreneur.created', subject: $profile, actor: $advisor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'stage' => EntrepreneurStage::INVITED->value,
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
            ]);

            return $profile;
        });

        return to_route('advisor.entrepreneurs.show', $profile)->with('status', 'entrepreneur-invited');
    }

    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
        $viewer = $this->actor($request);

        $entrepreneurProfile->loadMissing([
            'assignedAdvisor',
            'inviteToken',
            'user',
            'businessPlans.assessments.ratingFramework.criteria',
            'businessPlans.revisions',
        ]);
        $latestPlan = $entrepreneurProfile->businessPlans
            ->sortByDesc('updated_at')
            ->first();

        return Inertia::render('advisor/entrepreneurs/Show', [
            'entrepreneur' => [
                ...$this->profileSummary($entrepreneurProfile),
                'concept_summary' => $entrepreneurProfile->concept_summary,
                'user_id' => $entrepreneurProfile->user_id,
                'invite_accepted_at' => $entrepreneurProfile->inviteToken?->accepted_at?->toIso8601String(),
                'created_at' => $entrepreneurProfile->created_at?->toIso8601String(),
                'latest_plan' => $latestPlan instanceof BusinessPlan
                    ? $this->planProgressSummary($latestPlan, $entrepreneurProfile)
                    : null,
                'readiness' => $this->readinessSummary($entrepreneurProfile),
                'idea_validation' => $this->ideaValidationSummary($entrepreneurProfile),
                'advisory_readiness' => $this->advisoryReadinessSummary($entrepreneurProfile),
                'reports' => $this->reportSummary($entrepreneurProfile),
                'conversion' => $this->conversionSummary($entrepreneurProfile, $latestPlan),
                'documents' => $this->latestDocuments($entrepreneurProfile),
                'messages' => $this->messageSummary($entrepreneurProfile, $viewer),
            ],
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return Builder<EntrepreneurProfile>
     */
    private function visibleProfiles(User $user): Builder
    {
        $query = EntrepreneurProfile::query()
            ->with(['assignedAdvisor', 'inviteToken', 'user']);

        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return $query;
        }

        if ($user->user_type === User::TYPE_ENTREPRENEUR) {
            return $query->where('user_id', $user->getKey());
        }

        return $query->where('assigned_advisor_id', $user->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function profileSummary(EntrepreneurProfile $profile): array
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
            'assigned_advisor_name' => $profile->assignedAdvisor?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planProgressSummary(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $latestAssessment = $plan->assessments->sortByDesc('round')->first();
        $latestRevision = $plan->revisions->sortByDesc('round')->first();
        $latestAssessmentPayload = $latestAssessment instanceof PlanAssessment
            ? $this->assessmentPayload($latestAssessment)
            : null;

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'assessment_count' => $plan->assessments->count(),
            'latest_round' => $latestAssessment?->round,
            'latest_grade' => $latestAssessment?->overall_grade,
            'latest_assessment' => $latestAssessmentPayload ? [
                'id' => $latestAssessmentPayload['id'],
                'round' => $latestAssessmentPayload['round'],
                'status' => $latestAssessmentPayload['status'],
                'overall_grade' => $latestAssessmentPayload['overall_grade'],
                'weighted_score' => $latestAssessmentPayload['weighted_score'],
                'finalised_at' => $latestAssessmentPayload['finalised_at'],
                'url' => route('advisor.entrepreneurs.assessments.show', [$profile, $latestAssessment], absolute: false),
                'finalise_url' => route('advisor.entrepreneurs.assessments.finalise', [$profile, $latestAssessment], absolute: false),
            ] : null,
            'assess_url' => route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false),
            'latest_revision' => $latestRevision instanceof PlanRevision ? [
                'id' => $latestRevision->id,
                'round' => $latestRevision->round,
                'submitted_at' => $latestRevision->submitted_at?->toIso8601String(),
                'trajectory_percent' => data_get($latestRevision->progress_comparison, 'trajectory_percent'),
                'overall_delta' => data_get($latestRevision->progress_comparison, 'overall_delta'),
                'biggest_improvements' => data_get($latestRevision->progress_comparison, 'biggest_improvements', []),
                'remaining_gaps' => data_get($latestRevision->progress_comparison, 'remaining_gaps', []),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readinessSummary(EntrepreneurProfile $profile): array
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
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ideaValidationSummary(EntrepreneurProfile $profile): ?array
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
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'viability_alerts' => $validation->viability_alerts ?? [],
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'gate_url' => route('advisor.entrepreneurs.idea-validations.gate', [$profile, $validation], absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function advisoryReadinessSummary(EntrepreneurProfile $profile): ?array
    {
        $signal = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return null;
        }

        return [
            'id' => $signal->id,
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportSummary(EntrepreneurProfile $profile): array
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
                'generated_at' => $report->generated_at?->toIso8601String(),
                'download_url' => route('advisor.reports.download', $report, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionSummary(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signalExists = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->exists();

        return [
            'available' => $signalExists && ! $plan?->client_id,
            'converted' => $plan?->client_id !== null,
            'client_id' => $plan?->client_id,
            'convert_url' => route('advisor.entrepreneurs.convert', $profile, absolute: false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestDocuments(EntrepreneurProfile $profile): array
    {
        return Document::query()
            ->with('uploadedBy')
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'uploaded_by_name' => $document->uploadedBy?->name,
                'url' => route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageSummary(EntrepreneurProfile $profile, User $user): array
    {
        $threadIds = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id');
        $latestThread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->first();

        $participantRows = MessageThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', $user->getKey())
            ->get(['thread_id', 'last_read_at']);

        $unread = $participantRows->sum(function (MessageThreadParticipant $participant) use ($user): int {
            $query = Message::query()
                ->where('thread_id', $participant->thread_id)
                ->where('sender_user_id', '!=', $user->getKey());

            if ($participant->last_read_at !== null) {
                $query->where('sent_at', '>', $participant->last_read_at);
            }

            return $query->count();
        });

        return [
            'threads_count' => $threadIds->count(),
            'unread_count' => (int) $unread,
            'latest_activity_at' => $latestThread?->last_activity_at?->toIso8601String(),
            'url' => route('advisor.entrepreneurs.messages.index', $profile, absolute: false),
        ];
    }
}
