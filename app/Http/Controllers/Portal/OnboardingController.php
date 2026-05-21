<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use App\Services\Questionnaires\QuestionnairePayload;
use App\Services\Questionnaires\QuestionnaireResponseRecorder;
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

        return Inertia::render('portal/onboarding/Step', $this->payload($request, $client, $stepMeta));
    }

    public function store(Request $request, string $step): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $stepMeta = $this->wizard->step($step);

        if (! $this->wizard->canAccess($client, $step)) {
            return to_route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ])->with('status', 'onboarding-step-locked');
        }

        $payload = $this->validatedPayload($request, $step, $client);
        $this->wizard->saveStep($client, $step, $payload);

        /** @var User $user */
        $user = $request->user();
        $this->auditWriter->record('portal.onboarding_step_saved', subject: $client, actor: $user, after: [
            'step' => $step,
            'step_number' => $stepMeta['number'],
        ]);

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
            OnboardingWizard::STEP_DOCUMENTS => $request->validate([
                'documents_acknowledged' => ['accepted'],
            ]),
            OnboardingWizard::STEP_REVIEW => $request->validate([
                'review_confirmed' => ['accepted'],
            ]),
            default => abort(404),
        };
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
            $response = $this->responses->record($client, $user, $active, $request->all());

            return [
                'questionnaire_set' => $questionnaire['set'],
                'questionnaire_available' => true,
                'questionnaire_id' => $active->getKey(),
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

        $response = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->where('questionnaire_id', $active->getKey())
            ->with('answers')
            ->first();

        return [
            ...$meta,
            'schema' => $this->questionnairePayload->schema($active),
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
}
