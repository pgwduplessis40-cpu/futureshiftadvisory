<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\Scenario;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Goals\GoalTracker;
use App\Services\Notifications\NotificationCenter;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly OnboardingWizard $wizard,
        private readonly DataQualityScorer $dataQuality,
        private readonly NotificationCenter $notifications,
        private readonly GoalTracker $goals,
    ) {}

    public function __invoke(Request $request): Response
    {
        $client = $this->clients->resolveFor($request);

        return Inertia::render('portal/Dashboard', [
            'client' => $this->clientPayload($client),
            'progress' => $this->wizard->progress($client),
            'currentStep' => $this->wizard->currentStepSlug($client),
            'onboardingUrl' => route('portal.onboarding.step', [
                'step' => $this->wizard->currentStepSlug($client),
            ]),
            'notificationSummary' => $request->user() instanceof User
                ? $this->notifications->counts($request->user())
                : ['unread' => 0, 'urgent' => 0],
            'wellbeing' => $this->wellbeingPayload($client, $request->user()),
            'goals' => $this->goals->dashboard($client),
            'documents' => $this->documentPayload($client),
            'scenarios' => $this->scenarioPayload($client),
            'proposals' => $this->proposalPayload($client),
            'reports' => $this->reportPayload($client),
            'messagesUrl' => route('portal.messages.index', absolute: false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $dataQuality = $this->dataQuality->score($client);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'data_quality' => $dataQuality->level,
            'data_quality_summary' => $dataQuality->toPayload(),
            'nzbn' => $client->nzbn,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentPayload(Client $client): array
    {
        return Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->with('verifications')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'verification_state' => $this->documentVerificationState($document),
                'client_explanation' => $this->documentClientExplanation($document),
                'verifications' => $document->verifications
                    ->map(fn (DocumentVerification $verification): array => [
                        'id' => $verification->id,
                        'outcome' => $verification->outcome,
                        'claim_text' => $verification->claim_text,
                        'client_explanation' => $verification->clientFacingExplanation(),
                        'resolved_at' => $verification->resolved_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function wellbeingPayload(Client $client, mixed $user): array
    {
        $current = $user instanceof User
            ? WellbeingCheckin::query()
                ->where('client_id', $client->getKey())
                ->where('user_id', $user->getKey())
                ->whereDate('period_start', now()->startOfMonth()->toDateString())
                ->latest('submitted_at')
                ->first()
            : null;

        return [
            'prompt_due' => ! $current instanceof WellbeingCheckin,
            'period_start' => now()->startOfMonth()->toDateString(),
            'submitted_at' => $current?->submitted_at?->toIso8601String(),
            'url' => route('portal.wellbeing.show'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioPayload(Client $client): array
    {
        return Scenario::query()
            ->where('client_id', $client->getKey())
            ->where('is_client_visible', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->sortBy('position')
            ->map(fn (Scenario $scenario): array => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'kind' => $scenario->kind,
                'pv_impact' => $scenario->pv_impact,
                'position' => $scenario->position,
                'economic_overlay' => [
                    'applied_growth_rate' => $scenario->economic_overlay['applied_growth_rate'] ?? null,
                    'discount_method' => $scenario->economic_overlay['discount_method'] ?? null,
                    'indicators' => $scenario->economic_overlay['indicators'] ?? [],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalPayload(Client $client): array
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->whereIn('status', ['released', 'awaiting_signature', 'signed'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'version' => $proposal->version,
                'status' => $proposal->status->value,
                'status_label' => str($proposal->status->value)->replace('_', ' ')->title()->toString(),
                'suggested_mid' => $proposal->feeCalculation?->suggested_mid,
                'signed_at' => $proposal->signed_at?->toIso8601String(),
                'signoff_url' => route('portal.proposals.signoff.show', $proposal, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportPayload(Client $client): array
    {
        return Report::query()
            ->where('client_id', $client->getKey())
            ->where('type', 'client')
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'generated_at' => $report->generated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function documentVerificationState(Document $document): string
    {
        $outcomes = $document->verifications->pluck('outcome')->all();

        foreach ([
            DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            DocumentVerification::OUTCOME_ADVISORY_FLAG,
            DocumentVerification::OUTCOME_VERIFICATION_ERROR,
            DocumentVerification::OUTCOME_PENDING,
            DocumentVerification::OUTCOME_VERIFIED,
        ] as $outcome) {
            if (in_array($outcome, $outcomes, true)) {
                return $outcome;
            }
        }

        return DocumentVerification::OUTCOME_PENDING;
    }

    private function documentClientExplanation(Document $document): string
    {
        $verification = $document->verifications
            ->sortBy(fn (DocumentVerification $verification): int => match ($verification->outcome) {
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY => 0,
                DocumentVerification::OUTCOME_ADVISORY_FLAG => 1,
                DocumentVerification::OUTCOME_VERIFICATION_ERROR => 2,
                DocumentVerification::OUTCOME_PENDING => 3,
                default => 4,
            })
            ->first();

        return $verification instanceof DocumentVerification
            ? $verification->clientFacingExplanation()
            : 'Verification is in progress.';
    }
}
