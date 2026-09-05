<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\PortalWorkspaceDrafts;
use App\Services\ServiceActivations\ServiceActivationManager;
use App\Services\ServiceActivations\ServiceActivationNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceActivationController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly ServiceActivationManager $activations,
        private readonly ServiceActivationNavigation $navigation,
    ) {}

    public function create(Request $request, string $serviceType): Response|RedirectResponse
    {
        abort_unless(in_array($serviceType, [
            ServiceActivation::SERVICE_DUE_DILIGENCE,
            ServiceActivation::SERVICE_ENTREPRENEUR,
        ], true), 404);

        $client = $this->clients->resolveForServiceWorkspace($request);
        $currentActivation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', $serviceType)
            ->latest()
            ->get()
            ->first(fn (ServiceActivation $activation): bool => $activation->isOpen());

        if ($currentActivation instanceof ServiceActivation) {
            return to_route('portal.service-activations.show', $currentActivation);
        }

        $payload = $this->navigation->payload($client);
        $option = collect($payload['options'])
            ->first(fn (array $item): bool => $item['service_type'] === $serviceType);

        abort_unless(is_array($option), 404);

        return Inertia::render('portal/ServiceActivationRequest', [
            'service' => $option,
            'pricingPreview' => $this->activations->pricingPreviewForRequest($serviceType, includePackages: true, client: $client),
            'requestUrl' => route('portal.service-activations.store', absolute: false),
            'draftUrl' => route('portal.drafts.show', ['draftKey' => 'service-request:'.$serviceType], absolute: false),
            'dashboardUrl' => $this->dashboardUrl($request),
        ]);
    }

    public function store(Request $request, PortalWorkspaceDrafts $drafts): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'service_type' => ['required', 'string'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'asking_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'dd_experience' => [
                Rule::requiredIf(fn (): bool => $request->input('service_type') === ServiceActivation::SERVICE_DUE_DILIGENCE),
                'nullable',
                'string',
                Rule::in(['first_time', 'helped_before', 'completed_before']),
            ],
            'business_ownership_experience' => [
                Rule::requiredIf(fn (): bool => $request->input('service_type') === ServiceActivation::SERVICE_DUE_DILIGENCE),
                'nullable',
                'string',
                Rule::in(['none', 'managed_business', 'owned_business', 'bought_or_sold_business']),
            ],
            'financial_confidence' => [
                Rule::requiredIf(fn (): bool => $request->input('service_type') === ServiceActivation::SERVICE_DUE_DILIGENCE),
                'nullable',
                'string',
                Rule::in(['low', 'medium', 'high']),
            ],
            'preferred_guidance' => [
                Rule::requiredIf(fn (): bool => $request->input('service_type') === ServiceActivation::SERVICE_DUE_DILIGENCE),
                'nullable',
                'string',
                Rule::in(['guided', 'balanced', 'fast_track']),
            ],
            'idea_name' => ['nullable', 'string', 'max:255'],
            'customer' => ['nullable', 'string', 'max:255'],
            'problem' => ['nullable', 'string', 'max:1200'],
            'timing' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pricing_acknowledged' => ['accepted'],
            'pricing_package_id' => ['nullable', 'string', 'max:36'],
        ]);

        if (! in_array((string) $validated['service_type'], [
            ServiceActivation::SERVICE_DUE_DILIGENCE,
            ServiceActivation::SERVICE_ENTREPRENEUR,
        ], true)) {
            throw ValidationException::withMessages([
                'service_type' => 'This service is advisor-led. Message FSA to confirm the right scope and next step.',
            ]);
        }

        $pricingPreview = $this->activations->pricingPreviewForRequest(
            (string) $validated['service_type'],
            $validated,
            client: $client,
        );
        $matchedPackageId = data_get($pricingPreview, 'package.id');

        if ($matchedPackageId !== null && (string) ($validated['pricing_package_id'] ?? '') !== (string) $matchedPackageId) {
            throw ValidationException::withMessages([
                'pricing_acknowledged' => 'The displayed package fee has changed. Review the current package, scope, and fee before requesting access.',
            ]);
        }

        $activation = $this->activations->request(
            client: $client,
            actor: $user,
            serviceType: (string) $validated['service_type'],
            intake: $validated,
            pricingPreview: $pricingPreview,
        );
        $drafts->forget($user, 'service-request:'.(string) $validated['service_type']);

        return to_route('portal.service-activations.show', $activation)
            ->with('status', 'service-activation-requested');
    }

    public function show(Request $request, ServiceActivation $serviceActivation): Response
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $this->assertBelongsToClient($serviceActivation, $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $activation = $this->activations->applyPilotFeeWaiverIfEligible($serviceActivation->refresh(), $user);

        return Inertia::render('portal/ServiceActivation', [
            'activation' => $this->activationPayload($activation),
            'urls' => [
                'dashboard' => $this->dashboardUrl($request),
                'paymentComplete' => route('portal.service-activations.payment-complete', $serviceActivation, absolute: false),
                'accept' => route('portal.service-activations.accept', $serviceActivation, absolute: false),
                'ddWorkspace' => route('portal.dd-plan.show', absolute: false),
                'ideaWorkspace' => route('portal.entrepreneur.dashboard', absolute: false),
            ],
        ]);
    }

    public function paymentComplete(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $this->assertBelongsToClient($serviceActivation, $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->activations->completePayment($serviceActivation, $user);

        return to_route('portal.service-activations.show', $serviceActivation)
            ->with('status', 'service-activation-payment-complete');
    }

    public function accept(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $client = $this->clients->resolveForServiceWorkspace($request);
        $this->assertBelongsToClient($serviceActivation, $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate([
            'confirm_fee_scope' => ['accepted'],
        ]);

        $activation = $this->activations->accept($serviceActivation, $user);

        if ($activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE) {
            return to_route('portal.dd-plan.show')->with('status', 'service-activation-accepted');
        }

        if ($activation->service_type === ServiceActivation::SERVICE_DD_PLAN_BUDGET) {
            return to_route('portal.business-plan-budget.show')->with('status', 'service-activation-accepted');
        }

        if ($activation->service_type === ServiceActivation::SERVICE_ENTREPRENEUR) {
            return to_route('portal.entrepreneur.dashboard')->with('status', 'service-activation-accepted');
        }

        return to_route('portal.service-activations.show', $activation)
            ->with('status', 'service-activation-accepted');
    }

    private function assertBelongsToClient(ServiceActivation $activation, Client $client): void
    {
        abort_unless((string) $activation->client_id === (string) $client->getKey(), 404);
    }

    private function dashboardUrl(Request $request): string
    {
        return $request->user() instanceof User && $request->user()->user_type === User::TYPE_ENTREPRENEUR
            ? route('portal.entrepreneur.dashboard', absolute: false)
            : route('portal.dashboard', absolute: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function activationPayload(ServiceActivation $activation): array
    {
        $activation->loadMissing('package');
        $snapshot = $activation->selected_package_snapshot;

        return [
            'id' => $activation->id,
            'service_type' => $activation->service_type,
            'client_label' => $activation->clientLabel(),
            'status' => $activation->status,
            'status_label' => str($activation->status)->replace('_', ' ')->title()->toString(),
            'intake' => $activation->intake ?? [],
            'package' => is_array($snapshot) ? $snapshot : null,
            'request_pricing' => is_array(data_get($activation->metadata, 'pre_request_pricing'))
                ? data_get($activation->metadata, 'pre_request_pricing')
                : null,
            'payment_required' => $activation->paymentRequired(),
            'payment_status' => $activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED,
            'payment_status_label' => str((string) ($activation->payment_status ?? ServiceActivation::PAYMENT_NOT_REQUIRED))->replace('_', ' ')->title()->toString(),
            'payment_completed_at' => $activation->payment_completed_at?->toIso8601String(),
            'payment_reference' => $activation->payment_reference,
            'deposit_paid_at' => $activation->deposit_paid_at?->toIso8601String(),
            'deposit_reference' => $activation->deposit_reference,
            'balance_received_at' => $activation->balance_received_at?->toIso8601String(),
            'balance_reference' => $activation->balance_reference,
            'full_payment_received' => $activation->paymentComplete(),
            'acknowledgement_blocker' => $activation->acknowledgementBlocker(),
            'accepted_at' => $activation->accepted_at?->toIso8601String(),
            'acceptance_text' => $activation->acceptance_text,
            'workspace_ready' => $activation->status === ServiceActivation::STATUS_ACTIVE,
            'workspace_url' => $this->workspaceUrl($activation),
            'message_thread_url' => $activation->client_message_thread_id !== null
                ? route('portal.messages.show', $activation->client_message_thread_id, absolute: false)
                : null,
        ];
    }

    private function workspaceUrl(ServiceActivation $activation): ?string
    {
        if ($activation->status !== ServiceActivation::STATUS_ACTIVE) {
            return null;
        }

        return match ($activation->service_type) {
            ServiceActivation::SERVICE_DUE_DILIGENCE => route('portal.dd-plan.show', absolute: false),
            ServiceActivation::SERVICE_DD_PLAN_BUDGET => route('portal.business-plan-budget.show', absolute: false),
            ServiceActivation::SERVICE_ENTREPRENEUR => route('portal.entrepreneur.dashboard', absolute: false),
            default => null,
        };
    }
}
