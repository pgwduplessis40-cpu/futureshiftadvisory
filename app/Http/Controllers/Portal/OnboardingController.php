<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoTiritiMode;
use App\Enums\QuestionnaireSet;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\FunnelEvent;
use App\Models\NpoEngagement;
use App\Models\PostAcquisitionMigration;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Services\Audit\AuditWriter;
use App\Services\Npo\NpoQuestionnaireScoring;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use App\Services\Portal\PortalOfflineSync;
use App\Services\Questionnaires\QuestionnairePayload;
use App\Services\Questionnaires\QuestionnaireResponseRecorder;
use App\Services\Reports\ReportComposer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

final class OnboardingController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly OnboardingWizard $wizard,
        private readonly AuditWriter $auditWriter,
        private readonly QuestionnairePayload $questionnairePayload,
        private readonly QuestionnaireResponseRecorder $responses,
        private readonly NpoQuestionnaireScoring $npoQuestionnaireScoring,
        private readonly FunnelTracker $funnels,
        private readonly PortalOfflineSync $offlineSync,
        private readonly ReportComposer $reports,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);

        return to_route('portal.onboarding.step', [
            'step' => $this->wizard->currentStepSlug($client),
        ]);
    }

    public function show(Request $request, string $step): Response|RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $stepMeta = $this->wizard->step($step);

        if (! $this->wizard->canAccess($client, $step)) {
            return to_route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ])->with('status', 'onboarding-step-locked');
        }

        $user = $request->user();
        $this->funnels->enter(
            FunnelEvent::FLOW_ONBOARDING,
            $step,
            $client,
            $user instanceof User ? $user : null,
        );

        return Inertia::render('portal/onboarding/Step', $this->payload($request, $client, $stepMeta));
    }

    public function store(Request $request, string $step): RedirectResponse|JsonResponse
    {
        if ($this->offlineSync->isSync($request)) {
            return $this->offlineSync->handle(
                $request,
                "portal.onboarding.store:{$step}",
                fn (Client $client): JsonResponse => $this->storeForClient($request, $step, $client, true),
            );
        }

        $client = $this->clients->resolveFor($request);

        return $this->storeForClient($request, $step, $client, false);
    }

    private function storeForClient(Request $request, string $step, Client $client, bool $sync): RedirectResponse|JsonResponse
    {
        $stepMeta = $this->wizard->step($step);

        if (! $this->wizard->canAccess($client, $step)) {
            if ($sync) {
                return response()->json([
                    'ok' => false,
                    'operation' => 'portal.onboarding.store',
                    'status_slug' => 'onboarding-step-locked',
                    'step' => $step,
                ], 409);
            }

            return to_route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ])->with('status', 'onboarding-step-locked');
        }

        $payload = $this->validatedPayload($request, $step, $client);
        $this->wizard->saveStep($client, $step, $payload);

        /** @var User $user */
        $user = $request->user();
        $this->funnels->complete(FunnelEvent::FLOW_ONBOARDING, $step, $client, $user);
        $this->auditWriter->record('portal.onboarding_step_saved', subject: $client, actor: $user, after: [
            'step' => $step,
            'step_number' => $stepMeta['number'],
        ]);

        if ($sync) {
            $statusSlug = $step === OnboardingWizard::STEP_REVIEW
                ? 'onboarding-submitted'
                : 'onboarding-step-saved';

            return response()->json([
                'ok' => true,
                'operation' => 'portal.onboarding.store',
                'client_id' => $client->getKey(),
                'step' => $step,
                'status_slug' => $statusSlug,
                'next_step' => $this->wizard->currentStepSlug($client->refresh()),
            ]);
        }

        if ($step === OnboardingWizard::STEP_REVIEW) {
            return to_route('portal.dashboard')->with('status', 'onboarding-submitted');
        }

        return to_route('portal.onboarding.step', [
            'step' => $this->wizard->currentStepSlug($client->refresh()),
        ])->with('status', 'onboarding-step-saved');
    }

    /**
     * @param  array{number:int, slug:string, title:string, description:string}  $step
     * @return array<string, mixed>
     */
    private function payload(Request $request, Client $client, array $step): array
    {
        $state = $this->wizard->state($client);
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
                'engagement_type' => $engagementType->value,
                'engagement_type_label' => $engagementType->label(),
                'data_quality' => $client->data_quality,
                'nzbn' => $client->nzbn,
                'entity_type' => $client->entity_type,
                'gst_registered' => $client->gst_registered,
                'filing_status' => $client->filing_status,
            ],
            'step' => $step,
            'steps' => $this->wizard->navigation($client),
            'state' => $state,
            'stepData' => Arr::get($state, "steps.{$step['slug']}", []),
            'progress' => $this->wizard->progress($client),
            'questionnaire' => $this->questionnaireFor($client),
            'documentUploadUrl' => route('portal.documents.store'),
            'documentCount' => Document::query()
                ->visibleToClients()
                ->where('client_id', $client->getKey())
                ->count(),
            'submitUrl' => route('portal.onboarding.store', ['step' => $step['slug']]),
            'dashboardUrl' => route('portal.dashboard'),
            'authUser' => [
                'name' => (string) $request->user()?->name,
                'email' => (string) $request->user()?->email,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, string $step, Client $client): array
    {
        return match ($step) {
            OnboardingWizard::STEP_WELCOME => $request->validate([
                'acknowledged' => ['accepted'],
            ]),
            OnboardingWizard::STEP_IDENTITY => $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ]),
            OnboardingWizard::STEP_BUSINESS_SNAPSHOT => $request->validate([
                'snapshot_confirmed' => ['accepted'],
            ]),
            OnboardingWizard::STEP_GOALS => $request->validate([
                'primary_goal' => ['required', 'string', 'max:1000'],
                'success_measure' => ['nullable', 'string', 'max:1000'],
            ]),
            OnboardingWizard::STEP_QUESTIONNAIRE => $this->validateQuestionnaire($request, $client),
            OnboardingWizard::STEP_DOCUMENTS => $this->validateDocuments($request, $client),
            OnboardingWizard::STEP_REVIEW => $request->validate([
                'review_confirmed' => ['accepted'],
            ]),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocuments(Request $request, Client $client): array
    {
        $validated = $request->validate([
            'documents_acknowledged' => ['accepted'],
        ]);

        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::STANDARD_ADVISORY) {
            return $validated;
        }

        $hasDocument = Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->exists();

        if (! $hasDocument) {
            validator([], [
                'supporting_documents' => ['required'],
            ], [
                'supporting_documents.required' => 'Upload at least one supporting document before submitting Standard Advisory onboarding.',
            ])->validate();
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestionnaire(Request $request, Client $client): array
    {
        $questionnaire = $this->wizard->questionnaire($client);
        $active = $this->activeQuestionnaire($client);

        if ($questionnaire['available'] === true && $active instanceof Questionnaire) {
            /** @var User $user */
            $user = $request->user();
            $responseOptions = $this->responseOptions($client, (string) $questionnaire['set'], required: true);
            $active = $this->visibleQuestionnaireForClient($client, $active);
            $response = $this->responses->record($client, $user, $active, $request->all(), $responseOptions);
            $this->recordNpoQuestionnaireScores($client, (string) $questionnaire['set'], $responseOptions, $response, $user);
            $this->refreshPostAcquisitionGapReport($client, (string) $questionnaire['set'], $response, $user);

            return [
                'questionnaire_set' => $questionnaire['set'],
                'questionnaire_available' => true,
                'questionnaire_id' => $active->getKey(),
                'npo_engagement_id' => $responseOptions['npo_engagement_id'] ?? null,
                'response_id' => $response->getKey(),
            ];
        }

        $field = $questionnaire['available'] === true
            ? 'questionnaire_set_acknowledged'
            : 'phase_three_acknowledged';

        return [
            ...$request->validate([
                $field => ['accepted'],
            ]),
            'questionnaire_set' => $questionnaire['set'],
            'questionnaire_available' => $questionnaire['available'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionnaireFor(Client $client): array
    {
        $meta = $this->wizard->questionnaire($client);
        $active = $this->activeQuestionnaire($client);

        if (! $active instanceof Questionnaire) {
            return [
                ...$meta,
                'schema' => null,
                'answers' => [],
            ];
        }

        $responseOptions = $this->responseOptions($client, (string) $meta['set'], required: false);

        $response = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->where('npo_engagement_id', $responseOptions['npo_engagement_id'] ?? null)
            ->where('questionnaire_id', $active->getKey())
            ->with('answers')
            ->first();

        return [
            ...$meta,
            'npo_engagement_id' => $responseOptions['npo_engagement_id'] ?? null,
            'schema' => $this->questionnairePayload->schema($this->visibleQuestionnaireForClient($client, $active)),
            'answers' => $this->questionnairePayload->answers($response),
        ];
    }

    private function activeQuestionnaire(Client $client): ?Questionnaire
    {
        $meta = $this->wizard->questionnaire($client);
        if ($meta['available'] !== true) {
            return null;
        }

        return Questionnaire::query()
            ->forSet((string) $meta['set'])
            ->published()
            ->with('sections.questions')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{npo_engagement_id?: string|null}
     */
    private function responseOptions(Client $client, string $set, bool $required): array
    {
        if ($set === QuestionnaireSet::STANDARD_NPO->value) {
            $engagement = $this->fullNpoEngagement($client);
            abort_if(
                $required && ! $engagement instanceof NpoEngagement,
                422,
                'A full NPO engagement is required before submitting this questionnaire.',
            );

            return [
                'npo_engagement_id' => $engagement?->getKey(),
            ];
        }

        if ($set !== QuestionnaireSet::GOVERNANCE_REVIEW->value) {
            return ['npo_engagement_id' => null];
        }

        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        if ($engagementType !== EngagementType::NPO) {
            return ['npo_engagement_id' => null];
        }

        $engagement = $this->governanceReviewEngagement($client);
        abort_if(
            $required && ! $engagement instanceof NpoEngagement,
            422,
            'A governance review engagement is required before submitting this questionnaire.',
        );

        return [
            'npo_engagement_id' => $engagement?->getKey(),
        ];
    }

    /**
     * @param  array{npo_engagement_id?: string|null}  $responseOptions
     */
    private function recordNpoQuestionnaireScores(
        Client $client,
        string $set,
        array $responseOptions,
        QuestionnaireResponse $response,
        User $user,
    ): void {
        if ($set !== QuestionnaireSet::STANDARD_NPO->value) {
            return;
        }

        $engagementId = $responseOptions['npo_engagement_id'] ?? null;
        if (! is_string($engagementId)) {
            return;
        }

        $engagement = NpoEngagement::query()
            ->whereKey($engagementId)
            ->where('client_id', $client->getKey())
            ->first();

        if ($engagement instanceof NpoEngagement) {
            $this->npoQuestionnaireScoring->record($engagement, $response, $user);
        }
    }

    private function refreshPostAcquisitionGapReport(
        Client $client,
        string $set,
        QuestionnaireResponse $response,
        User $user,
    ): void {
        if ($set !== QuestionnaireSet::POST_ACQUISITION_GAP->value) {
            return;
        }

        $migration = PostAcquisitionMigration::query()
            ->where('advisory_client_id', $client->getKey())
            ->latest('migrated_at')
            ->latest()
            ->first();

        if (! $migration instanceof PostAcquisitionMigration) {
            return;
        }

        $migration->forceFill([
            'gap_questionnaire_response_id' => $response->getKey(),
            'metadata' => [
                ...($migration->metadata ?? []),
                'post_acquisition_gap_report_refreshed_at' => now()->toIso8601String(),
            ],
        ])->save();

        $report = $this->reports->composePostAcquisitionGap($migration->refresh(), $user);

        $migration->forceFill([
            'metadata' => [
                ...($migration->metadata ?? []),
                'post_acquisition_gap_report_id' => $report->getKey(),
                'post_acquisition_gap_report_refreshed_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function visibleQuestionnaireForClient(Client $client, Questionnaire $questionnaire): Questionnaire
    {
        if ($questionnaire->set !== QuestionnaireSet::STANDARD_NPO) {
            return $questionnaire;
        }

        $engagement = $this->fullNpoEngagement($client);
        $tiritiMode = $engagement?->tiriti_mode ?? NpoTiritiMode::Woven;

        if ($tiritiMode === NpoTiritiMode::Standalone) {
            return $questionnaire;
        }

        $questionnaire->setRelation(
            'sections',
            $questionnaire->sections
                ->reject(fn ($section): bool => (int) $section->order === 9 && $section->title === 'Te Tiriti')
                ->values(),
        );

        return $questionnaire;
    }

    private function fullNpoEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();
    }

    private function governanceReviewEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->latest()
            ->first();
    }
}
