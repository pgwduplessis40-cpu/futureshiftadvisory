<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Portal\ClientPortalResolver;
use App\Services\ServiceActivations\ServiceActivationManager;
use App\Services\ServiceActivations\ServiceActivationNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $client = $this->clients->resolveFor($request);
        $currentActivation = ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', $serviceType)
            ->latest()
            ->get()
            ->first(fn (ServiceActivation $activation): bool => $activation->isOpen());

        if ($currentActivation instanceof ServiceActivation) {
            if ($currentActivation->status === ServiceActivation::STATUS_ACTIVE) {
                return $currentActivation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE
                    ? to_route('portal.dd-plan.show')
                    : to_route('portal.entrepreneur.plan.show');
            }

            return to_route('portal.service-activations.show', $currentActivation);
        }

        $payload = $this->navigation->payload($client);
        $option = collect($payload['options'])
            ->first(fn (array $item): bool => $item['service_type'] === $serviceType);

        abort_unless(is_array($option), 404);

        return Inertia::render('portal/ServiceActivationRequest', [
            'service' => $option,
            'requestUrl' => route('portal.service-activations.store', absolute: false),
            'dashboardUrl' => route('portal.dashboard', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'service_type' => ['required', 'string'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'asking_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'idea_name' => ['nullable', 'string', 'max:255'],
            'customer' => ['nullable', 'string', 'max:255'],
            'problem' => ['nullable', 'string', 'max:1200'],
            'timing' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $activation = $this->activations->request(
            client: $client,
            actor: $user,
            serviceType: (string) $validated['service_type'],
            intake: $validated,
        );

        return to_route('portal.service-activations.show', $activation)
            ->with('status', 'service-activation-requested');
    }

    public function show(Request $request, ServiceActivation $serviceActivation): Response
    {
        $client = $this->clients->resolveFor($request);
        $this->assertBelongsToClient($serviceActivation, $client);

        return Inertia::render('portal/ServiceActivation', [
            'activation' => $this->activationPayload($serviceActivation->refresh()),
            'urls' => [
                'dashboard' => route('portal.dashboard', absolute: false),
                'paymentComplete' => route('portal.service-activations.payment-complete', $serviceActivation, absolute: false),
                'accept' => route('portal.service-activations.accept', $serviceActivation, absolute: false),
                'ddWorkspace' => route('portal.dd-plan.show', absolute: false),
                'ideaWorkspace' => route('portal.entrepreneur.plan.show', absolute: false),
            ],
        ]);
    }

    public function paymentComplete(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
        $this->assertBelongsToClient($serviceActivation, $client);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->activations->completePayment($serviceActivation, $user);

        return to_route('portal.service-activations.show', $serviceActivation)
            ->with('status', 'service-activation-payment-complete');
    }

    public function accept(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $client = $this->clients->resolveFor($request);
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

        return to_route('portal.entrepreneur.plan.show')->with('status', 'service-activation-accepted');
    }

    private function assertBelongsToClient(ServiceActivation $activation, Client $client): void
    {
        abort_unless((string) $activation->client_id === (string) $client->getKey(), 404);
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
            'accepted_at' => $activation->accepted_at?->toIso8601String(),
            'acceptance_text' => $activation->acceptance_text,
            'workspace_ready' => $activation->status === ServiceActivation::STATUS_ACTIVE,
            'workspace_url' => $activation->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE
                ? route('portal.dd-plan.show', absolute: false)
                : route('portal.entrepreneur.plan.show', absolute: false),
            'message_thread_url' => $activation->client_message_thread_id !== null
                ? route('portal.messages.show', $activation->client_message_thread_id, absolute: false)
                : null,
        ];
    }
}
